<?php
/**
 * Plugin Name: Migrago Platform
 * Description: Visa page logic (Discovery + Filtered UI only).
 * Version: 0.5.0
 * Author: Migrago
 */

if (!defined('ABSPATH')) {
    exit;
}


function mp_visa_is_front_context()
{
    if (is_admin()) {
        return false;
    }

    $service = sanitize_text_field(wp_unslash($_GET['service'] ?? ''));
    if ($service !== 'visa') {
        return false;
    }

    // Keep unrestricted by default; themes can lock this via the mp_visa_allowed_pages filter.
    $allowed_pages = apply_filters('mp_visa_allowed_pages', []);
    if (empty($allowed_pages)) {
        return true;
    }

    if (function_exists('is_page') && is_page($allowed_pages)) {
        return true;
    }

    return false;
}

function mp_visa_has_shortcode_context()
{
    if (is_admin()) {
        return false;
    }

    if (!is_singular()) {
        return false;
    }

    $post = get_post();
    if (!$post) {
        return false;
    }

    return has_shortcode($post->post_content, 'passport_ranking')
        || has_shortcode($post->post_content, 'featured_visa_offers')
        || has_shortcode($post->post_content, 'visa_search_form');
}

/* ==================================================
   REGISTER CPT
================================================== */
add_action('init', function () {
    register_post_type('visa_card', [
        'labels' => [
            'name' => 'بطاقات الفيزا',
            'singular_name' => 'بطاقة فيزا',
        ],
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-airplane',
        'supports' => ['title'],
    ]);

    register_post_type('visa_lead', [
        'labels' => [
            'name' => 'طلبات الفيزا',
            'singular_name' => 'طلب فيزا',
        ],
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-forms',
        'supports' => ['title'],
    ]);
});

/* ==================================================
   META BOX
================================================== */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'visa_details',
        'تفاصيل بطاقة الفيزا',
        'mp_visa_meta_html',
        'visa_card',
        'normal',
        'high'
    );
});

function mp_visa_meta_html($post)
{

    wp_nonce_field('save_visa_meta', 'visa_nonce');
    $get = function ($key) use ($post) {
        return get_post_meta($post->ID, $key, true);
    };
    ?>

    <p>
        <label>البلد الوجهة</label><br>
        <input type="text" name="destination" value="<?php echo esc_attr($get('destination')); ?>" style="width:100%">
    </p>

    <p>
        <label>الأغراض المتاحة لهذه الوجهة</label><br>
        <?php foreach (['tourist' => 'سياحة', 'study' => 'دراسة', 'work' => 'عمل', 'transit' => 'ترانزيت', 'medical' => 'علاج', 'family' => 'لم الشمل'] as $key => $label) : ?>
            <?php $field = 'purpose_enabled_' . $key; ?>
            <label style="display:block;margin:4px 0;">
                <input type="checkbox" name="<?php echo esc_attr($field); ?>" value="1" <?php checked($get($field), '1'); ?>>
                <?php echo esc_html($label); ?>
            </label>
        <?php endforeach; ?>
    </p>

    <p>
        <label>الجنسيات المسموح لها (افصل بينها بفاصلة)</label><br>
        <input type="text" name="allowed_nationalities" value="<?php echo esc_attr($get('allowed_nationalities')); ?>" style="width:100%">
    </p>

    <p>
        <label>بلد الإقامة</label><br>
        <input type="text" name="residency" value="<?php echo esc_attr($get('residency')); ?>" style="width:100%">
    </p>

    <p>
        <label>سعر الخدمة (مثال: 30$)</label><br>
        <input type="text" name="service_price" value="<?php echo esc_attr($get('service_price')); ?>" style="width:100%">
    </p>

    <p>
        <label>رابط صورة البطاقة</label><br>
        <input type="url" name="card_image" value="<?php echo esc_attr($get('card_image')); ?>" style="width:100%">
    </p>

    <p>
        <label>حالة التأشيرة</label><br>
        <select name="visa_status">
            <?php foreach (['no_visa' => 'بدون تأشيرة', 'visa_required' => 'تأشيرة مطلوبة', 'evisa' => 'تأشيرة إلكترونية', 'visa_on_arrival' => 'تأشيرة عند الوصول'] as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($get('visa_status'), $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label>مدة الإقامة (وصف مرن)</label><br>
        <input type="text" name="stay_description" value="<?php echo esc_attr($get('stay_description')); ?>" style="width:100%">
    </p>

    <p>
        <label>ملاحظات</label><br>
        <textarea name="note" style="width:100%"><?php echo esc_textarea($get('note')); ?></textarea>
    </p>

    <p>
        <label>متطلبات السياحة</label><br>
        <textarea name="requirements_tourist" style="width:100%"><?php echo esc_textarea($get('requirements_tourist')); ?></textarea>
    </p>

    <p>
        <label>متطلبات الدراسة</label><br>
        <textarea name="requirements_study" style="width:100%"><?php echo esc_textarea($get('requirements_study')); ?></textarea>
    </p>

    <p>
        <label>متطلبات العمل</label><br>
        <textarea name="requirements_work" style="width:100%"><?php echo esc_textarea($get('requirements_work')); ?></textarea>
    </p>

    <p>
        <label>متطلبات الترانزيت</label><br>
        <textarea name="requirements_transit" style="width:100%"><?php echo esc_textarea($get('requirements_transit')); ?></textarea>
    </p>

    <p>
        <label>متطلبات طبية</label><br>
        <textarea name="requirements_medical" style="width:100%"><?php echo esc_textarea($get('requirements_medical')); ?></textarea>
    </p>

    <p>
        <label>متطلبات لمّ الشمل</label><br>
        <textarea name="requirements_family" style="width:100%"><?php echo esc_textarea($get('requirements_family')); ?></textarea>
    </p>

    <p>
        <label>
            <input type="checkbox" name="service_enabled" value="1" <?php checked($get('service_enabled'), '1'); ?>>
            تفعيل خدمة التقديم
        </label>
    </p>

<?php }

/* ==================================================
   SAVE META
================================================== */
add_action('save_post', function ($post_id) {

    if (
        !isset($_POST['visa_nonce']) ||
        !wp_verify_nonce($_POST['visa_nonce'], 'save_visa_meta') ||
        get_post_type($post_id) !== 'visa_card'
    ) {
        return;
    }

    $text_fields = [
        'destination',
        'allowed_nationalities',
        'residency',
        'service_price',
        'card_image',
        'visa_status',
        'stay_description',
        'service_enabled',
    ];

    $textarea_fields = [
        'note',
        'requirements_tourist',
        'requirements_study',
        'requirements_work',
        'requirements_transit',
        'requirements_medical',
        'requirements_family',
    ];

    foreach ($text_fields as $field) {
        update_post_meta($post_id, $field, sanitize_text_field($_POST[$field] ?? ''));
    }

    foreach ($textarea_fields as $field) {
        update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$field] ?? ''));
    }

    foreach (['tourist', 'study', 'work', 'transit', 'medical', 'family'] as $purpose) {
        $field = 'purpose_enabled_' . $purpose;
        update_post_meta($post_id, $field, isset($_POST[$field]) ? '1' : '0');
    }

    update_post_meta($post_id, 'service_enabled', isset($_POST['service_enabled']) ? '1' : '0');
});

/* ==================================================
   RESULTS HEADER
================================================== */
add_action('migrago_results_header', function () {

    if (!mp_visa_is_front_context()) {
        return;
    }

    echo '
  <div class="visa-page-header">
    <h2>التحقق من الفيزا</h2>
    <p>اكتشف هل تحتاج إلى فيزا وما هو المسار المناسب لك</p>
  </div>';
});

/* ==================================================
   MAIN VISA PAGE LOGIC
================================================== */
add_action('migrago_render_results', function () {

    if (!mp_visa_is_front_context()) {
        return;
    }

    echo '<div class="results-wrapper">';

    $destination = sanitize_text_field($_GET['destination'] ?? '');
    $nationality = sanitize_text_field($_GET['nationality'] ?? '');
    $visa_purpose = sanitize_text_field($_GET['visa_purpose'] ?? $_GET['visa_type'] ?? '');
    $secondary_nationality = sanitize_text_field($_GET['secondary_nationality'] ?? $_GET['second_nationality'] ?? '');

    $has_search =
        !empty($destination) ||
        !empty($nationality) ||
        !empty($visa_purpose);

    /* =========================
       BEFORE SEARCH (DISCOVERY)
    ========================= */
    if (!$has_search) {

        echo '
    <div class="visa-discovery-intro">
      <h3>🌍 اكتشف وجهات الفيزا</h3>
      <p>هذه بعض الخيارات الشائعة. تحقق من حالتك لمعرفة التفاصيل.</p>
    </div>';

        $q = new WP_Query([
            'post_type' => 'visa_card',
            'post_status' => 'publish',
            'posts_per_page' => 6,
            'orderby' => 'rand',
        ]);

        if ($q->have_posts()) {
            echo '<div class="visa-results-grid">';
            while ($q->have_posts()) {
                $q->the_post();
                mp_render_visa_card([
                    'mode'  => 'discovery',
                    'title' => get_the_title(),
                    'stay'  => get_post_meta(get_the_ID(), 'stay_description', true),
                    'price' => get_post_meta(get_the_ID(), 'service_price', true),
                    'image' => get_post_meta(get_the_ID(), 'card_image', true),
                ]);
            }
            echo '</div>';
            wp_reset_postdata();
        }

        echo '</div>';
        return;
    }

    /* =========================
       AFTER SEARCH (FILTERED)
    ========================= */

    echo '
  <div class="visa-status-bar">
    <strong>🔎 حالتك الحالية</strong>
    <p>بناءً على معطياتك، هذه الخيارات المناسبة لك:</p>
  </div>';

    $q = new WP_Query([
        'post_type' => 'visa_card',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    ]);

    if (!$q->have_posts()) {
        echo '<p>لا توجد نتائج مطابقة.</p>';
        return;
    }

    $matched_cards = [];
    while ($q->have_posts()) {
        $q->the_post();
        $matches = mp_visa_matches_filters(get_the_ID(), $destination, $nationality, $visa_purpose, $secondary_nationality);
        if (!$matches) {
            continue;
        }

        $matched_cards[] = [
            'mode' => 'filtered',
            'title' => get_the_title(),
            'from_country' => $nationality,
            'to_country' => $destination,
            'stay' => get_post_meta(get_the_ID(), 'stay_description', true),
            'status' => get_post_meta(get_the_ID(), 'visa_status', true),
            'requirements' => mp_visa_requirements_for_purpose(get_the_ID(), $visa_purpose),
            'service_enabled' => get_post_meta(get_the_ID(), 'service_enabled', true),
            'price' => get_post_meta(get_the_ID(), 'service_price', true),
            'image' => get_post_meta(get_the_ID(), 'card_image', true),
            'purpose' => $visa_purpose,
        ];
    }

    wp_reset_postdata();

    $matches_found = count($matched_cards);
    if ($matches_found === 0) {
        echo '<p>لا توجد نتائج مطابقة لمعايير البحث الحالية.</p>';
        echo '</div>';
        return;
    }

    if ($matches_found === 1 && !empty($matched_cards[0]['service_enabled'])) {
        echo '<div class="visa-results-layout">';
        echo '<div class="visa-results-card">';
        mp_render_visa_card($matched_cards[0]);
        echo '</div>';
        echo '<div class="visa-results-form">';
        mp_visa_render_application_form($matched_cards[0]);
        echo '</div>';
        echo '</div>';
    } else {
        $grid_class = $matches_found === 1 ? 'visa-results-grid is-single' : 'visa-results-grid';
        echo '<div class="' . esc_attr($grid_class) . '">';
        foreach ($matched_cards as $card) {
            mp_render_visa_card($card);
        }
        echo '</div>';
    }
    echo '</div>';
});

/* ==================================================
   VISA CARD OUTPUT
================================================== */
function mp_visa_normalize_list($raw)
{
    $raw = strtolower($raw);
    $parts = array_filter(array_map('trim', explode(',', $raw)));
    return $parts;
}

function mp_visa_normalize_purpose($purpose)
{
    $purpose = strtolower(trim((string) $purpose));
    $map = [
        'tourist' => 'tourist',
        'study' => 'study',
        'work' => 'work',
        'transit' => 'transit',
        'medical' => 'medical',
        'family' => 'family',
        'سياحة' => 'tourist',
        'دراسة' => 'study',
        'عمل' => 'work',
        'ترانزيت' => 'transit',
        'علاج' => 'medical',
        'لم الشمل' => 'family',
    ];

    return $map[$purpose] ?? $purpose;
}

function mp_visa_matches_filters($post_id, $destination, $nationality, $purpose, $secondary_nationality)
{
    $destination_meta = strtolower(get_post_meta($post_id, 'destination', true));
    $allowed_raw = get_post_meta($post_id, 'allowed_nationalities', true);
    $allowed_list = mp_visa_normalize_list($allowed_raw);

    if (!empty($destination) && strtolower($destination) !== $destination_meta) {
        return false;
    }

    if (!empty($allowed_list)) {
        $nationalities = array_filter([
            strtolower($nationality),
            strtolower($secondary_nationality),
        ]);

        $allowed_all = in_array('all', $allowed_list, true) || in_array('*', $allowed_list, true);
        if (!$allowed_all && !array_intersect($allowed_list, $nationalities)) {
            return false;
        }
    }

    return true;
}

function mp_visa_requirements_for_purpose($post_id, $purpose)
{
    $map = [
        'tourist' => 'requirements_tourist',
        'study' => 'requirements_study',
        'work' => 'requirements_work',
        'transit' => 'requirements_transit',
        'medical' => 'requirements_medical',
        'family' => 'requirements_family',
    ];

    $purpose = mp_visa_normalize_purpose($purpose);
    if (empty($purpose)) {
        $purpose = mp_visa_normalize_purpose($_GET['visa_purpose'] ?? $_GET['visa_type'] ?? '');
    }

    if (empty($purpose) || !isset($map[$purpose])) {
        return 'يرجى اختيار الغرض لعرض المتطلبات المناسبة.';
    }

    $value = get_post_meta($post_id, $map[$purpose], true);
    if (!empty($value)) {
        return $value;
    }

    return 'لا توجد متطلبات محفوظة لهذا الغرض حالياً.';
}

function mp_visa_country_flag($country)
{
    $country = strtolower(trim((string) $country));
    $map = [
        'morocco' => '🇲🇦',
        'المغرب' => '🇲🇦',
        'egypt' => '🇪🇬',
        'مصر' => '🇪🇬',
        'turkey' => '🇹🇷',
        'تركيا' => '🇹🇷',
        'france' => '🇫🇷',
        'فرنسا' => '🇫🇷',
        'philippines' => '🇵🇭',
        'الفلبين' => '🇵🇭',
        'albania' => '🇦🇱',
        'البانيا' => '🇦🇱',
    ];

    return $map[$country] ?? '🌍';
}

function mp_visa_render_application_form($card)
{
    $action = esc_url(admin_url('admin-post.php'));
    $nonce = wp_create_nonce('mp_visa_lead');
    ?>
    <form method="post" action="<?php echo $action; ?>" class="visa-application-form" id="visa-application-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="mp_submit_visa_lead">
        <input type="hidden" name="mp_visa_lead_nonce" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="destination" value="<?php echo esc_attr($card['to_country'] ?? ''); ?>">
        <input type="hidden" name="nationality" value="<?php echo esc_attr($card['from_country'] ?? ''); ?>">
        <input type="hidden" name="visa_purpose" value="<?php echo esc_attr($card['purpose'] ?? ''); ?>">

        <div class="visa-form-header">
            <span class="visa-flag"><?php echo esc_html(mp_visa_country_flag($card['to_country'] ?? '')); ?></span>
            <div>
                <h4>نموذج طلب التأشيرة</h4>
                <p class="visa-form-note">يمكنك تعبئة البيانات على مراحل، وفريقنا سيتواصل معك مباشرة.</p>
            </div>
        </div>

        <div class="visa-form-section">
            <h5>المرحلة 1: معلومات المسافر</h5>
            <label>الاسم الكامل</label>
            <input type="text" name="full_name" placeholder="مثال: أحمد محمد" required>

            <label>اللقب</label>
            <input type="text" name="last_name" placeholder="مثال: العلوي" required>

            <label>رقم جواز السفر</label>
            <input type="text" name="passport_number" placeholder="P1234567" required>

            <label>رفع جواز السفر (PDF أو صورة)</label>
            <input type="file" name="passport_file">

            <label>البريد الإلكتروني</label>
            <input type="email" name="email" placeholder="example@email.com" required>

            <label>رقم الهاتف</label>
            <input type="text" name="phone" placeholder="+212 6xx xxx xxx" required>
        </div>

        <div class="visa-form-section">
            <h5>المرحلة 2: وثائق السفر والخدمات</h5>
            <label>حجز الفندق (اختياري)</label>
            <input type="file" name="hotel_booking">
            <label><input type="checkbox" name="need_hotel" value="yes"> لا أملك الحجز، أريد من فريقكم</label>

            <label>تذكرة الطيران (اختياري)</label>
            <input type="file" name="flight_booking">
            <label><input type="checkbox" name="need_flight" value="yes"> لا أملك التذكرة، أريد من فريقكم</label>

            <label>تأمين السفر (اختياري)</label>
            <input type="file" name="insurance_file">
            <label><input type="checkbox" name="need_insurance" value="yes"> لا أملك التأمين، أريد من فريقكم</label>
        </div>

        <div class="visa-form-section">
            <h5>المرحلة 3: وثائق إضافية</h5>
            <label>كشف حساب بنكي</label>
            <input type="file" name="bank_statement">

            <label>وثائق إضافية (PDF أو صور)</label>
            <input type="file" name="documents[]" multiple>

            <label><input type="checkbox" name="need_documents_help" value="yes"> لا أملك بعض الوثائق، أريد من فريقكم</label>
        </div>

        <div class="visa-form-section">
            <h5>المرحلة 4: خدمات إضافية</h5>
            <label><input type="checkbox" name="need_sim" value="yes"> أحتاج شريحة هاتف إلكترونية</label>
        </div>

        <label>ملاحظات إضافية</label>
        <textarea name="notes" rows="3" placeholder="أي تفاصيل إضافية"></textarea>

        <button type="submit" class="btn-primary btn-apply">إرسال الطلب</button>
    </form>
    <?php
}

add_action('admin_post_mp_submit_visa_lead', 'mp_handle_visa_lead_submission');
add_action('admin_post_nopriv_mp_submit_visa_lead', 'mp_handle_visa_lead_submission');

function mp_handle_visa_lead_submission()
{
    if (!isset($_POST['mp_visa_lead_nonce']) || !wp_verify_nonce($_POST['mp_visa_lead_nonce'], 'mp_visa_lead')) {
        wp_die('طلب غير صالح.');
    }

    $full_name = sanitize_text_field($_POST['full_name'] ?? '');
    $destination = sanitize_text_field($_POST['destination'] ?? '');
    $nationality = sanitize_text_field($_POST['nationality'] ?? '');
    $purpose = sanitize_text_field($_POST['visa_purpose'] ?? '');

    $lead_id = wp_insert_post([
        'post_type' => 'visa_lead',
        'post_status' => 'publish',
        'post_title' => trim($full_name) !== '' ? $full_name : 'طلب جديد',
    ]);

    if (!$lead_id || is_wp_error($lead_id)) {
        wp_die('تعذر حفظ الطلب.');
    }

    $fields = [
        'full_name' => $full_name,
        'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
        'passport_number' => sanitize_text_field($_POST['passport_number'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'phone' => sanitize_text_field($_POST['phone'] ?? ''),
        'destination' => $destination,
        'nationality' => $nationality,
        'visa_purpose' => $purpose,
        'need_flight' => isset($_POST['need_flight']) ? 'yes' : 'no',
        'need_hotel' => isset($_POST['need_hotel']) ? 'yes' : 'no',
        'need_sim' => isset($_POST['need_sim']) ? 'yes' : 'no',
        'need_insurance' => isset($_POST['need_insurance']) ? 'yes' : 'no',
        'need_documents_help' => isset($_POST['need_documents_help']) ? 'yes' : 'no',
        'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
    ];

    foreach ($fields as $key => $value) {
        update_post_meta($lead_id, $key, $value);
    }

    $single_files = [
        'passport_file' => 'passport_file',
        'hotel_booking' => 'hotel_booking',
        'flight_booking' => 'flight_booking',
        'insurance_file' => 'insurance_file',
        'bank_statement' => 'bank_statement',
    ];

    foreach ($single_files as $input => $meta_key) {
        if (!empty($_FILES[$input]['name'])) {
            $upload = wp_handle_upload($_FILES[$input], ['test_form' => false]);
            if (!empty($upload['file'])) {
                $attachment_id = wp_insert_attachment([
                    'post_title' => sanitize_file_name($_FILES[$input]['name']),
                    'post_type' => 'attachment',
                    'post_mime_type' => $upload['type'],
                    'post_status' => 'inherit',
                ], $upload['file']);
                if ($attachment_id && !is_wp_error($attachment_id)) {
                    update_post_meta($lead_id, $meta_key, $attachment_id);
                }
            }
        }
    }

    if (!empty($_FILES['documents']['name'][0])) {
        $uploaded_ids = [];
        foreach ($_FILES['documents']['name'] as $index => $name) {
            if (empty($name)) {
                continue;
            }
            $file = [
                'name' => $_FILES['documents']['name'][$index],
                'type' => $_FILES['documents']['type'][$index],
                'tmp_name' => $_FILES['documents']['tmp_name'][$index],
                'error' => $_FILES['documents']['error'][$index],
                'size' => $_FILES['documents']['size'][$index],
            ];
            $upload = wp_handle_upload($file, ['test_form' => false]);
            if (!empty($upload['file'])) {
                $attachment_id = wp_insert_attachment([
                    'post_title' => sanitize_file_name($file['name']),
                    'post_type' => 'attachment',
                    'post_mime_type' => $upload['type'],
                    'post_status' => 'inherit',
                ], $upload['file']);
                if ($attachment_id && !is_wp_error($attachment_id)) {
                    $uploaded_ids[] = $attachment_id;
                }
            }
        }
        if (!empty($uploaded_ids)) {
            update_post_meta($lead_id, 'documents', $uploaded_ids);
        }
    }

    $redirect = wp_get_referer() ?: home_url('/');
    wp_safe_redirect($redirect);
    exit;
}

function mp_visa_status_message($status)
{
    switch ($status) {
        case 'no_visa':
            return 'بدون تأشيرة - يمكنك السفر مباشرة.';
        case 'evisa':
            return 'تأشيرة إلكترونية - يمكنك التقديم عبر الإنترنت.';
        case 'visa_on_arrival':
            return 'تأشيرة عند الوصول - احصل عليها عند دخول البلد.';
        case 'visa_required':
        default:
            return 'مع الأسف، تحتاج إلى تأشيرة. يمكنك التقديم من خلال فريقنا.';
    }
}

function mp_render_visa_card($card)
{ ?>

    <div class="visa-card visa-card-<?php echo esc_attr($card['mode']); ?> visa-card--<?php echo esc_attr($card['mode']); ?>">

        <div class="visa-card-header">
            <span class="visa-flag"><?php echo esc_html(mp_visa_country_flag($card['title'] ?? '')); ?></span>
            <h4><?php echo esc_html($card['title']); ?></h4>
        </div>

        <?php if (!empty($card['price'])) : ?>
            <p class="visa-price">خدمة مميزة ابتداءً من: <strong><?php echo esc_html($card['price']); ?></strong></p>
        <?php endif; ?>

        <?php if ($card['mode'] === 'filtered' && (!empty($card['from_country']) || !empty($card['to_country']))) : ?>
            <div class="visa-route-ticket visa-route-<?php echo esc_attr($card['status'] ?? 'unknown'); ?>">
                <span class="route-country"><?php echo esc_html($card['from_country'] ?: 'بلدك'); ?></span>
                <span class="route-line">
                    <span class="route-dot"></span>
                    <span class="route-label"><?php echo esc_html(mp_visa_status_message($card['status'] ?? '')); ?></span>
                    <span class="route-dot"></span>
                </span>
                <span class="route-country"><?php echo esc_html($card['to_country'] ?: $card['title']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($card['stay'])) : ?>
            <p class="visa-stay">
                <strong>مدة الإقامة:</strong>
                <?php echo esc_html($card['stay']); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($card['status'])) : ?>
            <div class="visa-status-card visa-status-<?php echo esc_attr($card['status']); ?>">
                <?php echo esc_html(mp_visa_status_message($card['status'])); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($card['requirements'])) : ?>
            <div class="visa-requirements">
                <strong>الوثائق المطلوبة:</strong>
                <p><?php echo nl2br(esc_html($card['requirements'])); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($card['mode'] === 'discovery') : ?>
            <a class="btn-secondary btn-apply" href="#visa-search-form">
                تحقق من حالتك
            </a>
        <?php else : ?>
            <?php $cta = !empty($card['service_enabled']) ? 'تقديم الطلب' : 'تحقق من المتطلبات'; ?>
            <a class="btn-primary btn-apply" href="#visa-application-form">
                <?php echo esc_html($cta); ?>
            </a>
        <?php endif; ?>

    </div>

<?php }

/* ==================================================
   SHORTCODES
================================================== */
add_shortcode('passport_ranking', function () {
    return '<div class="passport-ranking-widget"><p>ترتيب جواز السفر سيتم تحديثه قريبًا.</p></div>';
});

add_shortcode('featured_visa_offers', function ($atts) {
    $atts = shortcode_atts([
        'limit' => 6,
    ], $atts, 'featured_visa_offers');

    $limit = max(1, absint($atts['limit']));
    $q = new WP_Query([
        'post_type' => 'visa_card',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'rand',
    ]);

    if (!$q->have_posts()) {
        return '<p>لا توجد عروض فيزا حالياً.</p>';
    }

    ob_start();
    echo '<div class="results-wrapper">';
    echo '<div class="visa-results-grid">';
    while ($q->have_posts()) {
        $q->the_post();
        mp_render_visa_card([
            'mode' => 'discovery',
            'title' => get_the_title(),
            'stay' => get_post_meta(get_the_ID(), 'stay_description', true),
            'price' => get_post_meta(get_the_ID(), 'service_price', true),
            'image' => get_post_meta(get_the_ID(), 'card_image', true),
        ]);
    }
    echo '</div>';
    echo '</div>';
    wp_reset_postdata();

    return ob_get_clean();
});

add_shortcode('visa_search_form', function () {
    $destination = esc_attr(sanitize_text_field($_GET['destination'] ?? ''));
    $nationality = esc_attr(sanitize_text_field($_GET['nationality'] ?? ''));
    $secondary_nationality = esc_attr(sanitize_text_field($_GET['secondary_nationality'] ?? $_GET['second_nationality'] ?? ''));
    $visa_purpose = esc_attr(sanitize_text_field($_GET['visa_purpose'] ?? $_GET['visa_type'] ?? ''));

    $action = esc_url(get_permalink());

    ob_start();
    ?>
    <div class="results-wrapper">
    <form method="get" action="<?php echo $action; ?>" class="visa-search-form" id="visa-search-form">
        <input type="hidden" name="service" value="visa">

        <p>
            <label>الجنسية</label><br>
            <input type="text" name="nationality" value="<?php echo $nationality; ?>" placeholder="مثال: moroccan">
        </p>

        <p>
            <label>هل لديك جنسية ثانية؟</label><br>
            <input type="text" name="secondary_nationality" value="<?php echo $secondary_nationality; ?>" placeholder="اختياري">
        </p>

        <p>
            <label>الوجهة</label><br>
            <input type="text" name="destination" value="<?php echo $destination; ?>" placeholder="مثال: turkey">
        </p>

        <p>
            <label>الغرض من السفر</label><br>
            <select name="visa_purpose">
                <option value="">اختر الغرض</option>
                <?php foreach (['tourist' => 'سياحة', 'study' => 'دراسة', 'work' => 'عمل', 'transit' => 'ترانزيت', 'medical' => 'علاج', 'family' => 'لم الشمل'] as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($visa_purpose, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <button type="submit" class="btn-primary btn-apply">تحقق الآن</button>
    </form>
    </div>
    <?php

    return ob_get_clean();
});

/* ==================================================
   ASSETS
================================================== */
add_action('wp_enqueue_scripts', function () {

    if (!mp_visa_is_front_context() && !mp_visa_has_shortcode_context()) {
        return;
    }

    wp_enqueue_style(
        'mp-visa-ui',
        plugin_dir_url(__FILE__) . 'assets/css/platform.css'
    );

});
