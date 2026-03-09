<?php
$receipt_id = (int) ($_GET['receipt_id'] ?? 0);
$client_id = (int) ($_GET['client_id'] ?? 0);

if ($receipt_id <= 0 && $client_id > 0) {
    $receipt_id = (int) get_post_meta($client_id, '_migrago_last_receipt_id', true);
}

$data = $receipt_id > 0 ? migrago_office_get_receipt_data($receipt_id) : [];

if ($receipt_id > 0 && is_array($data) && !empty($data) && isset($_GET['download'])) {
    $company = (array) ($data['company'] ?? migrago_office_company_profile());
    $vars = [
        'receipt_number' => (string) ($data['receipt_number'] ?? ''),
        'file_number' => (string) ($data['file_number'] ?? ''),
        'client_name' => esc_html((string) ($data['client_name'] ?? '')),
        'cin' => esc_html((string) ($data['cin'] ?? '')),
        'service_type' => esc_html((string) ($data['service_type'] ?? '')),
        'stage_label' => esc_html((string) ($data['stage_label'] ?? '')),
        'amount' => esc_html(number_format((float) ($data['amount'] ?? 0), 2)),
        'remaining' => esc_html(number_format((float) ($data['remaining'] ?? 0), 2)),
        'method' => esc_html((string) ($data['method'] ?? '')),
        'date' => esc_html((string) ($data['date'] ?? '')),
        'tracking_url' => esc_html((string) ($data['tracking_url'] ?? '')),
        'qr_image_tag' => !empty($data['qr_image']) ? '<img src="' . esc_url((string) $data['qr_image']) . '" alt="Tracking QR" width="140" height="140" />' : '',
        'company_name' => esc_html((string) ($company['name'] ?? '')),
        'company_description' => esc_html((string) ($company['description'] ?? '')),
        'company_address' => esc_html((string) ($company['address'] ?? '')),
        'company_phone' => esc_html((string) ($company['phone'] ?? '')),
        'company_ice' => esc_html((string) ($company['ice'] ?? '')),
    ];

    $template = migrago_office_get_document_template('receipt');
    $doc_html = migrago_office_render_document_template($template, $vars);
    $css = migrago_office_document_style_css('receipt');
    $full_html = '<html><head><meta charset="UTF-8"><style>' . esc_html($css) . '</style></head><body dir="rtl">' . wp_kses_post($doc_html) . '</body></html>';
    $file_number = (string) ($data['file_number'] ?? ('receipt-' . $receipt_id));
    $download_type = sanitize_key((string) $_GET['download']);

    if ($download_type === 'pdf') {
        $pdf_name = 'migrago-receipt-' . sanitize_file_name($file_number) . '.pdf';
        if (migrago_office_stream_pdf_document($full_html, $pdf_name)) {
            exit;
        }
        wp_die('تعذر توليد PDF حالياً. يرجى تفعيل wkhtmltopdf أو إحدى مكتبات PDF المدعومة على الخادم.', 'PDF غير متاح');
    }
}
?>
<div class="mo-receipt" dir="ltr">
    <?php if (!$receipt_id || !is_array($data) || empty($data)) : ?>
        <div class="mo-receipt-sheet">
            <h1 style="margin:0;">توصيل الدفع غير متوفر</h1>
            <p style="margin-top:8px;">لم يتم العثور على بيانات التوصيل. يرجى العودة لملف العميل ثم إنشاء/اختيار توصيل صحيح.</p>
        </div>
    <?php else : ?>
        <?php
        $company = (array) ($data['company'] ?? migrago_office_company_profile());
        $vars = [
            'receipt_number' => (string) ($data['receipt_number'] ?? ''),
            'file_number' => (string) ($data['file_number'] ?? ''),
            'client_name' => esc_html((string) ($data['client_name'] ?? '')),
            'cin' => esc_html((string) ($data['cin'] ?? '')),
            'service_type' => esc_html((string) ($data['service_type'] ?? '')),
            'stage_label' => esc_html((string) ($data['stage_label'] ?? '')),
            'amount' => esc_html(number_format((float) ($data['amount'] ?? 0), 2)),
            'remaining' => esc_html(number_format((float) ($data['remaining'] ?? 0), 2)),
            'method' => esc_html((string) ($data['method'] ?? '')),
            'date' => esc_html((string) ($data['date'] ?? '')),
            'tracking_url' => esc_html((string) ($data['tracking_url'] ?? '')),
            'qr_image_tag' => !empty($data['qr_image']) ? '<img src="' . esc_url((string) $data['qr_image']) . '" alt="Tracking QR" width="140" height="140" />' : '',
            'company_name' => esc_html((string) ($company['name'] ?? '')),
            'company_description' => esc_html((string) ($company['description'] ?? '')),
            'company_address' => esc_html((string) ($company['address'] ?? '')),
            'company_phone' => esc_html((string) ($company['phone'] ?? '')),
            'company_ice' => esc_html((string) ($company['ice'] ?? '')),
        ];

        $template = migrago_office_get_document_template('receipt');
        $html = migrago_office_render_document_template($template, $vars);
        ?>
        <?php
        echo wp_kses_post($html);
        $download_pdf_url = add_query_arg(
            [
                'receipt_id' => (string) $receipt_id,
                'client_id' => (string) $client_id,
                'download' => 'pdf',
            ],
            home_url('/receipt')
        );
        ?>
        <div class="mo-client-actions" style="justify-content:center;">
            <a href="<?php echo esc_url($download_pdf_url); ?>">تنزيل توصيل الدفع PDF</a>
        </div>
    <?php endif; ?>
</div>
