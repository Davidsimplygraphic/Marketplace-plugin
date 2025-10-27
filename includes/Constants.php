<?php
namespace SGPM;

if (!defined('ABSPATH')) exit;

class Constants {
    // Roles & % split
    const ROLE = 'provider';
    const COMMISSION_RATE = 0.80; // 80% provider

    // Endpoints (Woo My Account)
    const EP_PROVIDER_DETAILS = 'provider-details';
    const EP_YOUR_SERVICES    = 'your-services';

    // Nonces
    const NONCE_PD        = 'sgpm_save_provider_details';
    const NONCE_SVC       = 'sgpm_save_service';
    const NONCE_DEL       = 'sgpm_delete_service';
    const NONCE_SAMP      = 'sgpm_samples_submit';
    const NONCE_SAMP_DEL  = 'sgpm_sample_delete';
    const NONCE_SAMP_EDIT = 'sgpm_sample_edit';

    // User meta
    const META_SHOP_NAME    = 'sgpm_shop_name';
    const META_BIO          = 'sgpm_bio';
    const META_PAYOUT_TYPE  = 'sgpm_payout_type';
    const META_PAYOUT_EMAIL = 'sgpm_payout_email';
    const META_BANK_NAME    = 'sgpm_bank_name';
    const META_BANK_ACC     = 'sgpm_bank_acc';
    const META_BANK_BRANCH  = 'sgpm_bank_branch';
    const META_AVATAR_ID    = 'sgpm_avatar_id';
    const META_PROFILE_POST = 'sgpm_profile_post_id';

    // Samples
    const SAMPLE_FLAG_META = '_sgpm_sample';

    // CPT
    const CPT_PROFILE = 'provider_profile';
    // Endpoints
    const EP_ORDER_PORTAL = 'order-portal';
    const EP_WITHDRAWALS  = 'withdrawals';

    // CPT
    const CPT_CONVO = 'sgpm_conversation';

    // Nonces
    const NONCE_MSG       = 'sgpm_send_message';
    const NONCE_COMPLETE  = 'sgpm_mark_complete';
    const NONCE_RATING    = 'sgpm_save_rating';
    const NONCE_PAYOUT    = 'sgpm_request_payout';

    // Provider payout statuses (line-item meta)
    const PSTATUS_PENDING   = 'pending';    // paid, not complete
    const PSTATUS_AVAILABLE = 'available';  // complete, ready to withdraw
    const PSTATUS_REQUESTED = 'requested';  // provider asked for payout
    const PSTATUS_PAID      = 'paid';       // payout done
}
