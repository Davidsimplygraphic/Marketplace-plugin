<?php
namespace SGPM;

if (!defined('ABSPATH')) exit;

class Helpers {

    public static function is_provider(): bool {
        $u = wp_get_current_user();
        return $u && in_array(Constants::ROLE, (array)$u->roles, true);
    }

    public static function is_account_endpoint(string $endpoint): bool {
        if (!function_exists('is_account_page') || !is_account_page()) return false;
        global $wp;
        return isset($wp->query_vars[$endpoint]);
    }

    /**
     * Upload any file (image/video/audio/other). 
     * - Stores description on attachment post.
     * - ✅ Generates attachment metadata ONLY for images (so thumbnails/sizes work).
     * - ⛔ Skips metadata generation for videos/audio/others (avoids wp_read_video_metadata fatal).
     */
    public static function handle_file_upload(array $file, int $user_id, string $desc=''): int {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $move = wp_handle_upload($file, ['test_form' => false]);

        if ($move && !isset($move['error'])) {
            $type = wp_check_filetype($move['file'], null);
            $mime = (string) ($type['type'] ?? '');

            $attachment = [
                'post_mime_type' => $mime,
                'post_title'     => sanitize_file_name(basename($move['file'])),
                'post_content'   => $desc,
                'post_excerpt'   => $desc,
                'post_status'    => 'inherit',
                'post_author'    => $user_id,
            ];

            $attach_id = (int) wp_insert_attachment($attachment, $move['file']);

            // ✅ Only generate metadata for images (creates thumbs/sizes).
            if (strpos($mime, 'image/') === 0) {
                if (!function_exists('wp_generate_attachment_metadata')) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                }
                // @ to prevent noisy warnings if server image libs are missing
                $data = @wp_generate_attachment_metadata($attach_id, $move['file']);
                if (!is_wp_error($data) && is_array($data)) {
                    wp_update_attachment_metadata($attach_id, $data);
                }
            }

            return $attach_id;
        }

        return 0;
    }

    /** Convenience wrapper for single image uploads (no description) */
    public static function handle_image_upload(array $file, int $user_id): int {
        return self::handle_file_upload($file, $user_id, '');
    }

    /** Provider earnings summary from processing/completed orders */
    public static function get_provider_earnings_summary(int $provider_id): array {
        $orders = wc_get_orders([
            'type'   => 'shop_order',
            'status' => ['wc-processing','wc-completed'],
            'limit'  => -1,
            'return' => 'objects',
        ]);

        $sum = 0.0;
        $count = 0;

        foreach ($orders as $order) {
            foreach ($order->get_items('line_item') as $item) {
                if ((int)$item->get_meta('_provider_user_id', true) !== $provider_id) continue;

                $earn = $item->get_meta('_provider_earnings', true);
                if ($earn === '') {
                    $rate = (float) ($item->get_meta('_provider_commission_rate', true) ?: Constants::COMMISSION_RATE);
                    $earn = round(((float)$item->get_total()) * $rate, wc_get_price_decimals());
                } else {
                    $earn = (float)$earn;
                }

                $sum += $earn;
                $count++;
            }
        }

        return ['total' => $sum, 'count' => $count];
    }
    /**
 * Provider balances split by payout status.
 * Returns totals for: pending, available, requested, paid.
 */
public static function get_provider_balances(int $provider_id): array {
    // Pull relevant orders (processing/completed typically enough, but include others for safety)
    $orders = wc_get_orders([
        'type'   => 'shop_order',
        'status' => ['wc-processing','wc-completed','wc-on-hold','wc-refunded','wc-cancelled'],
        'limit'  => -1,
        'return' => 'objects',
    ]);

    $totals = [
        \SGPM\Constants::PSTATUS_PENDING   => 0.0,
        \SGPM\Constants::PSTATUS_AVAILABLE => 0.0,
        \SGPM\Constants::PSTATUS_REQUESTED => 0.0,
        \SGPM\Constants::PSTATUS_PAID      => 0.0,
    ];

    foreach ($orders as $order) {
        foreach ($order->get_items('line_item') as $item) {
            if ((int)$item->get_meta('_provider_user_id', true) !== $provider_id) continue;

            $amount = (float)$item->get_meta('_provider_earnings', true);
            if ($amount <= 0) continue;

            $status = (string)$item->get_meta('_provider_payout_status', true);
            if ($status === '') $status = \SGPM\Constants::PSTATUS_PENDING;
            if (!isset($totals[$status])) $status = \SGPM\Constants::PSTATUS_PENDING;

            $totals[$status] += $amount;
        }
    }

    return [
        'pending'   => $totals[\SGPM\Constants::PSTATUS_PENDING],
        'available' => $totals[\SGPM\Constants::PSTATUS_AVAILABLE],
        'requested' => $totals[\SGPM\Constants::PSTATUS_REQUESTED],
        'paid'      => $totals[\SGPM\Constants::PSTATUS_PAID],
    ];
}
}
