<?php
$contract_id = (int) ($_GET['contract_id'] ?? 0);
$client_id = (int) ($_GET['client_id'] ?? 0);

if ($contract_id <= 0 && $client_id > 0) {
    $contract_id = (int) get_post_meta($client_id, '_migrago_contract_id', true);
}

$data = $contract_id > 0 ? migrago_office_get_contract_data($contract_id) : [];

if ($contract_id > 0 && is_array($data) && !empty($data) && isset($_GET['download'])) {
    $download_type = sanitize_key((string) $_GET['download']);
    $doc_html = migrago_office_build_contract_document_html($contract_id);
    if ($doc_html !== '') {
        $css = migrago_office_document_style_css('contract');
        $full_html = '<html><head><meta charset="UTF-8"><style>' . esc_html($css) . '</style></head><body dir="rtl">' . wp_kses_post($doc_html) . '</body></html>';
        $file_number = (string) ($data['file_number'] ?? ('contract-' . $contract_id));

        if ($download_type === 'pdf') {
            $pdf_name = 'migrago-contract-' . sanitize_file_name($file_number) . '.pdf';
            if (migrago_office_stream_pdf_document($full_html, $pdf_name)) {
                exit;
            }
            wp_die('تعذر توليد PDF حالياً. يرجى تفعيل wkhtmltopdf أو إحدى مكتبات PDF المدعومة على الخادم.', 'PDF غير متاح');
        }
    }
}
?>
<div class="mo-receipt" dir="ltr">
    <?php if (!$contract_id || !is_array($data) || empty($data)) : ?>
        <div class="mo-receipt-sheet">
            <h1 style="margin:0;">عقد الخدمة غير متوفر</h1>
            <p style="margin-top:8px;">لم يتم العثور على بيانات العقد. يرجى العودة لملف العميل ثم إنشاء/اختيار عقد صحيح.</p>
        </div>
    <?php else : ?>
        <?php
        $html = migrago_office_build_contract_document_html($contract_id);
        echo wp_kses_post($html);
        $download_pdf_url = add_query_arg(
            [
                'contract_id' => (string) $contract_id,
                'client_id' => (string) $client_id,
                'download' => 'pdf',
            ],
            home_url('/contract')
        );
        ?>
        <div class="mo-client-actions" style="justify-content:center;">
            <a href="<?php echo esc_url($download_pdf_url); ?>">تنزيل عقد الخدمة PDF</a>
        </div>
    <?php endif; ?>
</div>
