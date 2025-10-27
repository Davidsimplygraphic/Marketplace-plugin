<?php
namespace SGPM\Modules\Account;

use SGPM\Constants;
use SGPM\Helpers;
use SGPM\Modules\Conversations;

if (!defined('ABSPATH')) exit;

class OrderPortal {
  public function init(): void {
    add_filter('woocommerce_account_menu_items', [$this,'menu_items']);
    add_action('woocommerce_account_' . Constants::EP_ORDER_PORTAL . '_endpoint', [$this,'render']);

    add_action('template_redirect', [$this,'handle_send_message']);
    add_action('template_redirect', [$this,'handle_mark_complete']);
    add_action('template_redirect', [$this,'handle_save_rating']);
  }

  public function menu_items($items){
    if (!is_user_logged_in()) return $items;
    // Put after Orders
    $out=[];
    foreach ($items as $k=>$label){
      $out[$k]=$label;
      if ($k==='orders') $out[Constants::EP_ORDER_PORTAL]=__('Workrooms','provider-marketplace');
    }
    if (!isset($out[Constants::EP_ORDER_PORTAL])) $out[Constants::EP_ORDER_PORTAL]=__('Workrooms','provider-marketplace');
    return $out;
  }

  public function render(){
    if (!is_user_logged_in()){ echo esc_html__('Login required.','provider-marketplace'); return; }
    $uid = get_current_user_id();

    $convo_id = isset($_GET['c']) ? absint($_GET['c']) : 0;
    if ($convo_id) {
      if (!Conversations::user_can_access($convo_id, $uid)){ echo esc_html__('Access denied.','provider-marketplace'); return; }

      $order_id  = (int)get_post_meta($convo_id, '_order_id', true);
      $item_id   = (int)get_post_meta($convo_id, '_order_item_id', true);
      $job_status= get_post_meta($convo_id, '_job_status', true);
      $messages  = Conversations::get_messages($convo_id);

      echo '<h3>'.esc_html__('Workroom','provider-marketplace').'</h3>';

      // Messages thread
      echo '<div class="sgpm-thread" style="border:1px solid #eee;border-radius:8px;padding:12px;max-width:860px;">';
      if ($messages){
        foreach ($messages as $m){
          $author = get_userdata((int)$m->user_id);
          echo '<div style="margin-bottom:10px">';
          echo '<div style="font-weight:600">'.esc_html($author ? $author->display_name : 'User').'</div>';
          echo '<div>'.wp_kses_post(wpautop($m->comment_content)).'</div>';
          $atts = get_comment_meta($m->comment_ID, '_sgpm_attachment_id');
          if ($atts){
            echo '<div style="margin-top:6px">';
            foreach ($atts as $aid){
              $url = wp_get_attachment_url((int)$aid);
              echo '<a href="'.esc_url($url).'" target="_blank" rel="noopener">'.esc_html(basename($url)).'</a> ';
            }
            echo '</div>';
          }
          echo '</div><hr style="border:none;border-top:1px solid #f0f0f0">';
        }
      } else {
        echo '<p>'.esc_html__('No messages yet.','provider-marketplace').'</p>';
      }

      // Send message form
      ?>
      <form method="post" enctype="multipart/form-data" style="margin-top:10px">
        <?php wp_nonce_field(Constants::NONCE_MSG, Constants::NONCE_MSG); ?>
        <textarea name="sgpm_msg" rows="3" placeholder="<?php esc_attr_e('Write a message…','provider-marketplace'); ?>" required></textarea>
        <div><input type="file" name="sgpm_files[]" multiple></div>
        <input type="hidden" name="sgpm_convo_id" value="<?php echo esc_attr($convo_id); ?>">
        <p><button class="button button-primary" type="submit"><?php esc_html_e('Send','provider-marketplace'); ?></button></p>
      </form>
      <?php
      echo '</div>';

      // Completion + rating (customer only)
      $order = wc_get_order($order_id);
      $is_customer = $order && (int)$order->get_user_id() === $uid;

      if ($is_customer){
        // Mark complete
        if ($job_status !== 'completed'){
          ?>
          <form method="post" style="margin-top:1rem">
            <?php wp_nonce_field(Constants::NONCE_COMPLETE, Constants::NONCE_COMPLETE); ?>
            <input type="hidden" name="sgpm_convo_id" value="<?php echo esc_attr($convo_id); ?>">
            <button class="button" type="submit"><?php esc_html_e('Mark Job Complete','provider-marketplace'); ?></button>
          </form>
          <?php
        } else {
          // Rating form (only if not rated yet)
          $already_rated = get_post_meta($convo_id, '_sgpm_rated', true);
          if (!$already_rated){
            ?>
            <form method="post" style="margin-top:1rem;max-width:600px">
              <?php wp_nonce_field(Constants::NONCE_RATING, Constants::NONCE_RATING); ?>
              <label><?php esc_html_e('Rate your provider','provider-marketplace'); ?></label>
              <select name="sgpm_rating" required>
                <option value=""><?php esc_html_e('Select…','provider-marketplace'); ?></option>
                <?php for($i=5;$i>=1;$i--) echo '<option value="'.$i.'">'.$i.' ★</option>'; ?>
              </select>
              <textarea name="sgpm_review" rows="3" placeholder="<?php esc_attr_e('Say a few words about the experience (optional)','provider-marketplace'); ?>"></textarea>
              <input type="hidden" name="sgpm_convo_id" value="<?php echo esc_attr($convo_id); ?>">
              <p><button class="button button-primary" type="submit"><?php esc_html_e('Submit Review','provider-marketplace'); ?></button></p>
            </form>
            <?php
          } else {
            echo '<p style="margin-top:1rem">'.esc_html__('Thanks! Your review was submitted.','provider-marketplace').'</p>';
          }
        }
      }

      // Back link
      echo '<p><a class="button" href="'.esc_url(wc_get_account_endpoint_url(Constants::EP_ORDER_PORTAL)).'">&larr; '.esc_html__('All Workrooms','provider-marketplace').'</a></p>';
      return;
    }

    // List
    $convos = Conversations::get_convos_for_user($uid);
    echo '<h3>'.esc_html__('Messages For Orders','provider-marketplace').'</h3>';
    if (!$convos){ echo '<p>'.esc_html__('No workrooms yet.','provider-marketplace').'</p>'; return; }

    echo '<table class="shop_table shop_table_responsive"><thead><tr>
      <th>'.esc_html__('Order','provider-marketplace').'</th>
      <th>'.esc_html__('Item','provider-marketplace').'</th>
      <th>'.esc_html__('Status','provider-marketplace').'</th>
      <th>'.esc_html__('Action','provider-marketplace').'</th>
    </tr></thead><tbody>';

    foreach ($convos as $p){
      $order_id  = (int)get_post_meta($p->ID, '_order_id', true);
      $item_id   = (int)get_post_meta($p->ID, '_order_item_id', true);
      $job_status= get_post_meta($p->ID, '_job_status', true);
      $order     = wc_get_order($order_id);
      $item_name = $order ? ($order->get_item($item_id)->get_name() ?? '') : '';
      echo '<tr>';
      echo '<td>#'.esc_html($order_id).'</td>';
      echo '<td>'.esc_html($item_name).'</td>';
      echo '<td>'.esc_html(ucfirst($job_status ?: 'open')).'</td>';
      echo '<td><a class="button" href="'.esc_url(add_query_arg('c',$p->ID, wc_get_account_endpoint_url(Constants::EP_ORDER_PORTAL))).'">'.esc_html__('Open','provider-marketplace').'</a></td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  /* ---------- handlers ---------- */

  public function handle_send_message(){
    if (!is_user_logged_in() || !Helpers::is_account_endpoint(Constants::EP_ORDER_PORTAL)) return;
    if (!isset($_POST[Constants::NONCE_MSG]) || !wp_verify_nonce($_POST[Constants::NONCE_MSG], Constants::NONCE_MSG)) return;

    $uid = get_current_user_id();
    $convo_id = absint($_POST['sgpm_convo_id'] ?? 0);
    if (!$convo_id || !Conversations::user_can_access($convo_id, $uid)) return;

    $content = wp_kses_post($_POST['sgpm_msg'] ?? '');
    $files = [];
    if (!empty($_FILES['sgpm_files']['name'][0])) {
      foreach ($_FILES['sgpm_files']['name'] as $i=>$name) {
        if (!$name) continue;
        $files[] = [
          'name'=>$_FILES['sgpm_files']['name'][$i],
          'type'=>$_FILES['sgpm_files']['type'][$i],
          'tmp_name'=>$_FILES['sgpm_files']['tmp_name'][$i],
          'error'=>$_FILES['sgpm_files']['error'][$i],
          'size'=>$_FILES['sgpm_files']['size'][$i],
        ];
      }
    }
    \SGPM\Modules\Conversations::add_message($convo_id, $uid, $content, $files);
    wc_add_notice(__('Message sent.','provider-marketplace'));
    wp_safe_redirect(add_query_arg('c',$convo_id, wc_get_account_endpoint_url(Constants::EP_ORDER_PORTAL)));
    exit;
  }

  public function handle_mark_complete(){
    if (!is_user_logged_in() || !Helpers::is_account_endpoint(Constants::EP_ORDER_PORTAL)) return;
    if (!isset($_POST[Constants::NONCE_COMPLETE]) || !wp_verify_nonce($_POST[Constants::NONCE_COMPLETE], Constants::NONCE_COMPLETE)) return;

    $uid = get_current_user_id();
    $convo_id = absint($_POST['sgpm_convo_id'] ?? 0);
    $order_id = (int)get_post_meta($convo_id, '_order_id', true);
    $item_id  = (int)get_post_meta($convo_id, '_order_item_id', true);
    $order    = wc_get_order($order_id);
    if (!$order || (int)$order->get_user_id() !== $uid) return; // customer only

    // Flip job status
    update_post_meta($convo_id, '_job_status', 'completed');

    // Make provider earnings AVAILABLE for this line
    $item = $order->get_item($item_id);
    if ($item){
      $item->update_meta_data('_provider_payout_status', Constants::PSTATUS_AVAILABLE);
      $item->save();
    }

    wc_add_notice(__('Job marked complete. You can now leave a review.','provider-marketplace'));
    wp_safe_redirect(add_query_arg('c',$convo_id, wc_get_account_endpoint_url(Constants::EP_ORDER_PORTAL)));
    exit;
  }

  public function handle_save_rating(){
    if (!is_user_logged_in() || !Helpers::is_account_endpoint(Constants::EP_ORDER_PORTAL)) return;
    if (!isset($_POST[Constants::NONCE_RATING]) || !wp_verify_nonce($_POST[Constants::NONCE_RATING], Constants::NONCE_RATING)) return;

    $uid = get_current_user_id();
    $convo_id = absint($_POST['sgpm_convo_id'] ?? 0);
    $order_id = (int)get_post_meta($convo_id, '_order_id', true);
    $item_id  = (int)get_post_meta($convo_id, '_order_item_id', true);
    $provider = (int)get_post_meta($convo_id, '_provider_id', true);
    $order    = wc_get_order($order_id);

    if (!$order || (int)$order->get_user_id() !== $uid) return;
    if (get_post_meta($convo_id, '_job_status', true) !== 'completed') return;

    $rating = max(1, min(5, (int)($_POST['sgpm_rating'] ?? 0)));
    $text   = wp_kses_post($_POST['sgpm_review'] ?? '');

    // Store as comment on the provider's profile (or fallback to user)
    $profile_id = (int) get_user_meta($provider, \SGPM\Constants::META_PROFILE_POST, true);
    $post_for_review = $profile_id ?: 0;

    $cid = wp_insert_comment([
      'comment_post_ID' => $post_for_review,
      'user_id'         => $uid,
      'comment_content' => $text,
      'comment_type'    => 'sgpm_provider_review',
      'comment_approved'=> 1,
    ]);
    if ($cid){
      add_comment_meta($cid, '_sgpm_rating', $rating);
      add_comment_meta($cid, '_order_id', $order_id);
      add_comment_meta($cid, '_order_item_id', $item_id);
      add_comment_meta($cid, '_provider_id', $provider);
      update_post_meta($convo_id, '_sgpm_rated', 1);
      wc_add_notice(__('Review submitted. Thank you!','provider-marketplace'));
    }
    wp_safe_redirect(add_query_arg('c',$convo_id, wc_get_account_endpoint_url(Constants::EP_ORDER_PORTAL)));
    exit;
  }
}
