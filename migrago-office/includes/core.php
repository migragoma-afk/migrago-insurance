<?php

if (!defined('ABSPATH')) {
    exit;
}

function migrago_office_staff_capabilities(): array
{
    return [
        'read' => true,
        'upload_files' => true,
        'migrago_manage_own_clients' => true,
        'migrago_create_client' => true,
        'migrago_record_payment' => true,
        'migrago_upload_contract' => true,
        'migrago_upload_receipt' => true,
        'migrago_search_client' => true,
    ];
}

function migrago_office_admin_extra_capabilities(): array
{
    return [
        'migrago_manage_members' => true,
        'migrago_manage_clients' => true,
        'migrago_update_workflow' => true,
        'migrago_approve_reject_files' => true,
        'migrago_view_analytics' => true,
        'migrago_manage_accounting' => true,
        'migrago_confirm_bank_transfer' => true,
        'migrago_cancel_payment' => true,
        'migrago_view_financial_reports' => true,
    ];
}

function migrago_office_register_roles(): void
{
    add_role(
        MIGRAGO_OFFICE_ROLE_ADMIN,
        __('Migrago Office Admin', 'migrago-office'),
        array_merge(migrago_office_staff_capabilities(), migrago_office_admin_extra_capabilities())
    );

    add_role(
        MIGRAGO_OFFICE_ROLE_MEMBER,
        __('Migrago Team Member', 'migrago-office'),
        migrago_office_staff_capabilities()
    );
}


function migrago_office_sync_roles_capabilities(): void
{
    $admin_caps = array_merge(migrago_office_staff_capabilities(), migrago_office_admin_extra_capabilities());
    $staff_caps = migrago_office_staff_capabilities();

    $admin_role = get_role(MIGRAGO_OFFICE_ROLE_ADMIN);
    if (!$admin_role) {
        add_role(MIGRAGO_OFFICE_ROLE_ADMIN, __('Migrago Office Admin', 'migrago-office'), $admin_caps);
        $admin_role = get_role(MIGRAGO_OFFICE_ROLE_ADMIN);
    }

    if ($admin_role) {
        foreach ($admin_caps as $cap => $grant) {
            if ($grant) {
                $admin_role->add_cap($cap);
            }
        }
    }

    $staff_role = get_role(MIGRAGO_OFFICE_ROLE_MEMBER);
    if (!$staff_role) {
        add_role(MIGRAGO_OFFICE_ROLE_MEMBER, __('Migrago Team Member', 'migrago-office'), $staff_caps);
        $staff_role = get_role(MIGRAGO_OFFICE_ROLE_MEMBER);
    }

    if ($staff_role) {
        foreach ($staff_caps as $cap => $grant) {
            if ($grant) {
                $staff_role->add_cap($cap);
            }
        }
    }
}

function migrago_office_is_admin(): bool
{
    if (!function_exists('wp_get_current_user')) {
        return false;
    }

    $user = wp_get_current_user();

    return in_array(MIGRAGO_OFFICE_ROLE_ADMIN, (array) ($user->roles ?? []), true) || current_user_can('manage_options');
}

function migrago_office_user_role_label(int $user_id = 0): string
{
    if (!function_exists('wp_get_current_user') || !class_exists('WP_User')) {
        return 'unknown';
    }

    $user = $user_id > 0 ? get_user_by('id', $user_id) : wp_get_current_user();
    if (!$user instanceof WP_User) {
        return 'unknown';
    }

    return in_array(MIGRAGO_OFFICE_ROLE_ADMIN, (array) ($user->roles ?? []), true) || in_array('administrator', (array) ($user->roles ?? []), true)
        ? 'admin'
        : 'staff';
}


function migrago_office_company_profile(): array
{
    return [
        'name' => 'MIGRAGO',
        'description' => 'Cabinet de Mobilité Internationale',
        'vision' => 'Faciliter la mobilité internationale avec transparence, sécurité et accompagnement professionnel.',
        'address' => '210 Bd Anoual, 2ème étage, Casablanca, Maroc',
        'phone' => '05 22 48 69 45',
        'email' => 'contact@migrago.ma',
        'website' => 'www.migrago.ma',
        'ice' => '00376443500056',
        'rc' => '684803',
        'if' => '34108358',
    ];
}



function migrago_office_contract_types(): array
{
    return [
        'employment' => 'عقد التوظيف',
        'study' => 'عقد الدراسة',
    ];
}

function migrago_office_get_contract_legal_content(string $type): string
{
    $supported = migrago_office_contract_types();
    $normalized = isset($supported[$type]) ? $type : 'employment';
    $option_name = 'migrago_office_contract_legal_content_' . $normalized;
    $saved = (string) get_option($option_name, '');

    if ($saved !== '') {
        return $saved;
    }

    return migrago_office_default_contract_legal_content($normalized);
}

function migrago_office_document_style_css(string $type): string
{
    $option = $type === 'contract' ? 'migrago_office_contract_document_css' : 'migrago_office_receipt_document_css';
    $saved = (string) get_option($option, '');
    if ($saved !== '') {
        return $saved;
    }

    return "body{font-family:Tahoma,Arial,sans-serif;font-size:14px;color:#111;}"
        . ".mo-receipt-sheet{background:#fff;border:2px solid #111;border-radius:12px;padding:18px;max-width:820px;margin:0 auto;}"
        . "h1,h2,h3{margin:0 0 10px;}"
        . "table{width:100%;border-collapse:collapse;}"
        . "td,th{border:1px solid #ddd;padding:8px;}"
        . "hr{border:none;border-top:1px solid #ddd;margin:14px 0;}";
}

function migrago_office_stream_pdf_document(string $html, string $filename): bool
{
    $tmp_html = wp_tempnam('migrago_pdf_html_');
    $tmp_pdf = wp_tempnam('migrago_pdf_out_');

    if ($tmp_html && $tmp_pdf && function_exists('shell_exec')) {
        file_put_contents($tmp_html, $html);

        $command = trim((string) shell_exec('command -v wkhtmltopdf 2>/dev/null'));
        if ($command !== '') {
            $cmd = escapeshellcmd($command) . ' --encoding UTF-8 --quiet ' . escapeshellarg($tmp_html) . ' ' . escapeshellarg($tmp_pdf) . ' 2>/dev/null';
            shell_exec($cmd);

            if (is_file($tmp_pdf) && filesize($tmp_pdf) > 0) {
                nocache_headers();
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                readfile($tmp_pdf);

                @unlink($tmp_html);
                @unlink($tmp_pdf);
                return true;
            }
        }

        @unlink($tmp_html);
        @unlink($tmp_pdf);
    }

    if (class_exists('\Dompdf\Dompdf')) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        return true;
    }

    if (class_exists('TCPDF')) {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Migrago Office');
        $pdf->SetAuthor('Migrago Office');
        $pdf->SetTitle($filename);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        nocache_headers();
        $pdf->Output($filename, 'D');
        return true;
    }

    return false;
}

function migrago_office_default_receipt_template(): string
{
    return '<div class="mo-receipt-sheet">
'
        . '<h1 style="text-align:center;margin-bottom:6px;">إيصال أداء / PAYMENT RECEIPT</h1>
'
        . '<p style="text-align:center;margin-top:0;color:#444;">{{company_name}} - {{company_description}}</p>
'
        . '<table>
'
        . '<tr><th>رقم التوصيل</th><td>{{receipt_number}}</td><th>رقم الملف</th><td>{{file_number}}</td></tr>
'
        . '<tr><th>اسم العميل</th><td>{{client_name}}</td><th>رقم البطاقة</th><td>{{cin}}</td></tr>
'
        . '<tr><th>نوع الخدمة</th><td>{{service_type}}</td><th>المرحلة</th><td>{{stage_label}}</td></tr>
'
        . '<tr><th>المبلغ المؤدى</th><td>{{amount}} MAD</td><th>المتبقي</th><td>{{remaining}} MAD</td></tr>
'
        . '<tr><th>طريقة الأداء</th><td>{{method}}</td><th>التاريخ</th><td>{{date}}</td></tr>
'
        . '</table>
'
        . '<div style="text-align:center;margin:12px 0;">{{qr_image_tag}}<br><small>{{tracking_url}}</small></div>
'
        . '<p style="margin:18px 0 0;">نشهد أن العميل المذكور أعلاه أدى المبلغ المبين في هذا الإيصال.</p>
'
        . '<div style="margin-top:34px;display:flex;justify-content:space-between;"><div>توقيع العميل</div><div>خاتم وتوقيع الشركة</div></div>
'
        . '<hr>
'
        . '<p style="font-size:12px;color:#444;margin:0;">{{company_name}} | {{company_address}} | Tel: {{company_phone}} | ICE: {{company_ice}}</p>
'
        . '</div>';
}

function migrago_office_default_contract_template(): string
{
    return '<div class="mo-receipt-sheet">
'
        . '<div style="text-align:center;margin:0 0 8px;">{{qr_image_tag}}</div>
'
        . '<h1 style="margin-bottom:0;">{{company_name}}</h1>
'
        . '<p style="margin-top:4px;">{{company_description}}</p>
'
        . '<hr>
'
        . '<p><strong>Contract N°:</strong> {{contract_number}} | <strong>Dossier N°:</strong> {{file_number}}</p>
'
        . '<p><strong>Client:</strong> {{client_name}}</p>
'
        . '<p><strong>Service:</strong> {{service_type}}</p>
'
        . '<p><strong>Montant Total:</strong> {{agreed_amount}} MAD</p>
'
        . '<p><strong>Étape actuelle:</strong> {{stage_label}}</p>
'
        . '<div style="margin:10px 0;padding:10px;border:1px solid #ddd;">{{contract_content}}</div>
'
        . '<div style="text-align:center;margin:12px 0;"><small>{{tracking_url}}</small></div>
'
        . '<div style="margin-top:45px;display:flex;justify-content:space-between;"><div>Signature Client</div><div>Cachet & Signature Société</div></div>
'
        . '<hr style="margin-top:28px;">
'
        . '<p style="font-size:12px;color:#444;margin:0;">{{company_name}} | {{company_address}} | Tel: {{company_phone}} | ICE: {{company_ice}}</p>
'
        . '</div>';
}

function migrago_office_default_contract_legal_content(string $type = 'employment'): string
{
    if ($type === 'study') {
        return '<h2 style="text-align:center;margin:0 0 16px;">عقد تقديم خدمات التوجيه والاستشارة الدراسية</h2>'
            . '<p><strong>الطرف الأول:</strong> شركة MIGRAGO (SARL) - السجل التجاري 684803 - ICE: 003764435000056</p>'
            . '<p><strong>الطرف الثاني:</strong> {{client_name}} - البطاقة: {{cin}} - الجواز: {{passport_number}}</p>'
            . '<p>يتعهد المكتب بمرافقة الطرف الثاني في خدمات التوجيه الدراسي وإعداد الملف والتتبع الإداري وفق الشروط المتفق عليها.</p>'
            . '<p><strong>المبلغ المتفق عليه:</strong> {{agreed_amount}} درهم.</p>'
            . '<p>يمكن تعديل البنود والشروط كاملة من لوحة التحكم حسب سياسة الشركة.</p>'
            . '<p>حرر بمدينة: {{contract_city}} بتاريخ: {{contract_date_pretty}}</p>';
    }

    return '<h2 style="text-align:center;margin:0 0 16px;">عقد تقديم خدمات الوساطة والتوجيه والاستشارة في التوظيف</h2>'
        . '<table style="width:100%;border-collapse:collapse;margin-bottom:14px;">'
        . '<tr>'
        . '<td style="width:50%;vertical-align:top;padding:10px;border:1px solid #ddd;">'
        . '<strong>الطرف الأول (الشركة)</strong><br>'
        . 'شركة: MIGRAGO (SARL)<br>'
        . 'السجل التجاري: 684803<br>'
        . 'رقم التعريف الموحد للمقاولة (ICE): 003764435000056<br>'
        . 'ممثلها القانوني أو من يحل محله والكائن مقرها الإجتماعي في العنوان التالي: لالة اليقوت، شارع العرعار، الدار البيضاء رقم المبنى 1 طابق 3 رقم شقة 8.<br>'
        . 'ويشار إليها فيما بعد بـ "المكتب".'
        . '</td>'
        . '<td style="width:50%;vertical-align:top;padding:10px;border:1px solid #ddd;">'
        . '<strong>الطرف الثاني (المترشح/ة)</strong><br>'
        . 'الاسم: {{client_name}}<br>'
        . 'تاريخ الازدياد: {{client_birth_date}}<br>'
        . 'مكان الازدياد: {{client_birth_place}}<br>'
        . 'رقم البطاقة الوطنية: {{cin}}<br>'
        . 'رقم جواز السفر: {{passport_number}}<br>'
        . 'العنوان: {{client_address}}<br>'
        . 'ويشار إليها فيما بعد بـ "الطرف الثاني".'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '<p>وبما أن الطرف الأول يزاول نشاط الوساطة في التوظيف المتمثل في توجيه ومرافقة الباحثين عن العمل وربطهم بالمؤسسات المشغّلة داخل أو خارج المغرب، فقد اتفق الطرفان على ما يلي:</p>'
        . '<h3>المادة الأولى: التزامات المكتب تجاه المترشح</h3>'
        . '<ol>'
        . '<li>يلتزم المكتب بالبحث عن فرصة عمل مناسبة للمترشح لدى مؤسسة تشغيل معتمدة ومرخّصة بدول الاتحاد الأوروبي وبحسب التخصص الذي يختاره الطرف الثاني.</li>'
        . '<li>يقدم المكتب خدمة الاستشارة المهنية، بما في ذلك: توجيه المترشح لاختيار المنصب المناسب، وشرح ظروف العمل والأجور والعقود والسكن إن توفر ونظام التأشيرة والسفر.</li>'
        . '<li>يتكلف المكتب بإعداد ملف المترشح وإيداعه لدى جهات التشغيل، ومتابعة جميع مراحل قبول الطلب والتواصل مع صاحب العمل.</li>'
        . '<li>لا يتحمل المكتب أي مسؤولية عن قرارات صاحب العمل المتعلقة بالقبول أو الرفض النهائي لطلب التشغيل.</li>'
        . '<li>إجراءات منح التأشيرة من اختصاص السلطات القنصلية وحدها، والمكتب غير مسؤول عن أي تأخير أو رفض أو تغيير في المواعيد.</li>'
        . '<li>يلتزم المكتب، بعد حصول المترشح على عقد العمل أو دعوة العمل، بالتنسيق مع الجهة المشغِّلة لإبلاغهم بتاريخ وصول المترشح.</li>'
        . '<li>يُعفى المكتب من أي مسؤولية عن ترتيبات الوصول في المطار أو السكن في حال سفر الطرف الثاني دون إشعار المكتب مسبقاً.</li>'
        . '</ol>'
        . '<h3>المادة الثانية: التزامات المترشح تجاه المكتب</h3>'
        . '<ol>'
        . '<li>يلتزم الطرف الثاني بتقديم جميع الوثائق الصحيحة واللازمة لمعالجة ملف التوظيف، ويكون وحده مسؤولاً عن أي تزوير أو تحريف في الوثائق.</li>'
        . '<li>يلتزم المترشح بأداء مبلغ إجمالي قدره <strong>{{agreed_amount}} درهم</strong> مقابل خدمات المكتب، ويُدفع على ثلاث مراحل: 3000 درهم كرسوم فتح ملف بعد توقيع هذا العقد، و15000 درهم بعد موافقة المشغّل كرسوم تجهيز عقد العمل، وباقي المبلغ بعد صدور عقد العمل النهائي. مع الإشارة إلى أن رسوم تجهيز العقد (15000 درهم) تُسترجع في حال عدم صدور عقد العمل، ولا يُعتبر أي مبلغ مدفوعاً ما لم يكن مقابلاً لوصل أداء رسمي يحمل ختم المكتب.</li>'
        . '<li>الوثائق المطلوبة تشمل: جواز السفر + البطاقة الوطنية، السيرة الذاتية، الشواهد المهنية أو الدراسية، وأي وثائق إضافية يطلبها المشغّل.</li>'
        . '</ol>'
        . '<h3>المادة الثالثة: الشروط العامة</h3>'
        . '<ol>'
        . '<li>يسترجع المترشح كامل المبلغ المدفوع للمكتب في حالة واحدة فقط: إذا لم يستطع المكتب توفير قبول تشغيل نهائي من المؤسسة المشغِّلة المتفق عليها لأي سبب.</li>'
        . '<li>في حال تراجع المترشح عن إتمام إجراءات التوظيف أو السفر لأي سبب، فلا يحق له استرجاع أي مبالغ سبق دفعها للمكتب.</li>'
        . '<li>في حال رفض التأشيرة من قبل القنصلية، فإن المكتب غير ملزم بإرجاع المبالغ التي تم دفعها نظير خدماته، لكنه يلتزم بمساعدة المترشح على استرجاع المبلغ المدفوع للمشغّل إن وُجد، باستثناء الرسوم الإدارية التي يحددها المشغّل.</li>'
        . '<li>في حال حصول المترشح على التأشيرة ثم قرر عدم السفر، فإن الجهة المشغِّلة غير ملزمة بإرجاع أي مبالغ سبق دفعها.</li>'
        . '<li>المكتب غير مسؤول عن أي سلوكيات شخصية للطرف الثاني بعد وصوله إلى دولة العمل.</li>'
        . '<li>تحتفظ الجهة المشغّلة بحقها في فسخ العقد أو طرد المترشح عند سوء السلوك أو الغياب المتكرر أو مخالفة القانون.</li>'
        . '<li>يُعفى الطرفان من التزامات العقد في حال وقوع قوة قاهرة مثل: كوارث وطنية أو حرب أو قرارات حكومية مانعة أو أي أمر خارج عن إرادة أي من الطرفين.</li>'
        . '<li>في حالة نشوء نزاع بين المكتب والمترشح، ينعقد الاختصاص للمحكمة التجارية التي يقع ضمن نفوذها مقر المكتب، ويمكن اللجوء للتحكيم في حال اتفاق الطرفين.</li>'
        . '</ol>'
        . '<h3>إقرار المترشح</h3>'
        . '<p>أنا الموقع أسفله، الطرف الثاني، أقر أنني قرأت جميع بنود هذا العقد وفهمتها وأوافق على الشروط المذكورة أعلاه، وعلى ذلك تم توقيع هذا العقد بين الطرفين.</p>'
        . '<table style="width:100%;margin-top:18px;border-collapse:collapse;">'
        . '<tr><td style="padding:8px;border:1px solid #ddd;text-align:center;">توقيع الشركة</td><td style="padding:8px;border:1px solid #ddd;text-align:center;">توقيع الطرف الثاني</td></tr>'
        . '<tr><td style="height:70px;border:1px solid #ddd;"></td><td style="height:70px;border:1px solid #ddd;"></td></tr>'
        . '</table>'
        . '<p style="margin-top:14px;">حرر بمدينة: {{contract_city}} بتاريخ: {{contract_date_pretty}}</p>';
}

function migrago_office_get_document_template(string $type): string
{
    $option = $type === 'contract' ? 'migrago_office_contract_template_html' : 'migrago_office_receipt_template_html';
    $saved = (string) get_option($option, '');
    if ($saved !== '') {
        return $saved;
    }

    return $type === 'contract' ? migrago_office_default_contract_template() : migrago_office_default_receipt_template();
}

function migrago_office_contract_template_key_from_service(string $service_type): string
{
    if ($service_type === 'employment') {
        return 'employment';
    }

    if ($service_type === 'study') {
        return 'study';
    }

    return 'other';
}

function migrago_office_default_full_contract_template(string $template_key): string
{
    if ($template_key === 'study') {
        return '<div class="mo-receipt-sheet"><div style="text-align:center;margin:0 0 8px;">{{qr_image_tag}}</div><h1 style="margin-bottom:0;">{{company_name}}</h1><p style="margin-top:4px;">{{company_description}}</p><hr><p><strong>نوع العقد:</strong> عقد الدراسة | <strong>رقم الملف:</strong> {{file_number}}</p><p><strong>العميل:</strong> {{client_name}} | <strong>رقم البطاقة:</strong> {{cin}}</p><p><strong>الخدمة:</strong> {{service_type}} | <strong>المبلغ:</strong> {{agreed_amount}} MAD</p><div style="margin:10px 0;padding:10px;border:1px solid #ddd;">{{contract_content}}</div><div style="text-align:center;margin:12px 0;"><small>{{tracking_url}}</small></div><div style="margin-top:45px;display:flex;justify-content:space-between;"><div>توقيع العميل</div><div>خاتم وتوقيع الشركة</div></div><hr style="margin-top:28px;"><p style="font-size:12px;color:#444;margin:0;">{{company_name}} | {{company_address}} | Tel: {{company_phone}} | ICE: {{company_ice}}</p></div>';
    }

    if ($template_key === 'other') {
        return '<div class="mo-receipt-sheet"><div style="text-align:center;margin:0 0 8px;">{{qr_image_tag}}</div><h1 style="margin-bottom:0;">{{company_name}}</h1><p style="margin-top:4px;">{{company_description}}</p><hr><p><strong>نوع العقد:</strong> عقد خدمات أخرى | <strong>رقم الملف:</strong> {{file_number}}</p><p><strong>العميل:</strong> {{client_name}}</p><p><strong>الخدمة:</strong> {{service_type}}</p><div style="margin:10px 0;padding:10px;border:1px solid #ddd;">{{contract_content}}</div><div style="text-align:center;margin:12px 0;"><small>{{tracking_url}}</small></div><div style="margin-top:45px;display:flex;justify-content:space-between;"><div>توقيع العميل</div><div>خاتم وتوقيع الشركة</div></div></div>';
    }

    return '<div class="mo-receipt-sheet"><div style="text-align:center;margin:0 0 8px;">{{qr_image_tag}}</div><h1 style="margin-bottom:0;">{{company_name}}</h1><p style="margin-top:4px;">{{company_description}}</p><hr><p><strong>نوع العقد:</strong> عقد العمل | <strong>رقم الملف:</strong> {{file_number}}</p><p><strong>العميل:</strong> {{client_name}} | <strong>رقم البطاقة:</strong> {{cin}}</p><p><strong>الخدمة:</strong> {{service_type}} | <strong>المبلغ:</strong> {{agreed_amount}} MAD</p><div style="margin:10px 0;padding:10px;border:1px solid #ddd;">{{contract_content}}</div><div style="text-align:center;margin:12px 0;"><small>{{tracking_url}}</small></div><div style="margin-top:45px;display:flex;justify-content:space-between;"><div>توقيع العميل</div><div>خاتم وتوقيع الشركة</div></div><hr style="margin-top:28px;"><p style="font-size:12px;color:#444;margin:0;">{{company_name}} | {{company_address}} | Tel: {{company_phone}} | ICE: {{company_ice}}</p></div>';
}

function migrago_office_get_full_contract_template(string $service_type): string
{
    $key = migrago_office_contract_template_key_from_service($service_type);
    $saved = (string) get_option('migrago_office_contract_full_template_' . $key, '');
    if ($saved !== '') {
        return $saved;
    }

    return migrago_office_default_full_contract_template($key);
}

function migrago_office_render_document_template(string $template, array $vars): string
{
    $replacements = [];
    foreach ($vars as $key => $value) {
        $replacements['{{' . $key . '}}'] = (string) $value;
    }

    return strtr($template, $replacements);
}

function migrago_office_register_post_types(): void
{
    register_post_type('migrago_client', [
        'label' => __('Clients', 'migrago-office'),
        'public' => false,
        'show_ui' => true,
        'map_meta_cap' => false,
        'capabilities' => [
            'create_posts' => 'migrago_create_client',
            'edit_posts' => 'migrago_manage_own_clients',
            'publish_posts' => 'migrago_create_client',
            'read_private_posts' => 'migrago_search_client',
            'delete_posts' => 'migrago_manage_clients',
            'edit_post' => 'migrago_manage_own_clients',
            'read_post' => 'read',
            'delete_post' => 'migrago_manage_clients',
        ],
        'supports' => ['title'],
    ]);

    register_post_type('migrago_audit_log', [
        'label' => __('Migrago Audit Logs', 'migrago-office'),
        'public' => false,
        'show_ui' => true,
        'supports' => ['title', 'editor'],
    ]);

    register_post_type('migrago_finance_entry', [
        'label' => __('Finance Entries', 'migrago-office'),
        'public' => false,
        'show_ui' => true,
        'supports' => ['title'],
    ]);

    register_post_type('migrago_receipt', [
        'label' => __('Receipts', 'migrago-office'),
        'public' => false,
        'show_ui' => true,
        'map_meta_cap' => false,
        'capabilities' => [
            'create_posts' => 'migrago_upload_receipt',
            'edit_posts' => 'migrago_upload_receipt',
            'publish_posts' => 'migrago_upload_receipt',
            'read_post' => 'read',
        ],
        'supports' => ['title'],
    ]);

    register_post_type('migrago_contract', [
        'label' => __('Contracts', 'migrago-office'),
        'public' => false,
        'show_ui' => true,
        'map_meta_cap' => false,
        'capabilities' => [
            'create_posts' => 'migrago_upload_contract',
            'edit_posts' => 'migrago_upload_contract',
            'publish_posts' => 'migrago_upload_contract',
            'read_post' => 'read',
        ],
        'supports' => ['title','editor'],
    ]);
}

function migrago_office_log_action(string $action, string $entity_type, int $entity_id = 0, array $details = []): void
{
    $user_id = get_current_user_id();
    $payload = [
        'action' => $action,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'details' => $details,
        'user_id' => $user_id,
        'role' => migrago_office_user_role_label($user_id),
        'datetime' => current_time('mysql'),
    ];

    wp_insert_post([
        'post_type' => 'migrago_audit_log',
        'post_status' => 'publish',
        'post_title' => sprintf('%s - %s #%d', $action, $entity_type, $entity_id),
        'post_content' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'post_author' => $user_id,
    ]);
}

function migrago_office_service_types(): array
{
    return ['visa', 'study', 'employment', 'insurance', 'other'];
}

function migrago_office_payment_methods(): array
{
    return ['cash', 'bank_transfer', 'card', 'paypal', 'cashplus'];
}


function migrago_office_remaining_amount(int $client_id): float
{
    if ($client_id <= 0) {
        return 0.0;
    }

    $agreed = (float) get_post_meta($client_id, '_migrago_agreed_amount', true);
    $paid = (float) get_post_meta($client_id, '_migrago_paid_amount', true);

    return max(0.0, $agreed - $paid);
}

function migrago_office_finance_categories(string $type): array
{
    $income = ['client_payment', 'bank_transfer', 'other_income'];
    $expense = ['rent', 'internet', 'water', 'phone', 'purchases', 'salary', 'marketing', 'other_expense'];

    return $type === 'income' ? $income : $expense;
}

function migrago_office_workflow_stages(): array
{
    return [
        'file_created' => __('تم إنشاء الملف', 'migrago-office'),
        'processing' => __('قيد المعالجة', 'migrago-office'),
        'submitted' => __('تم التقديم', 'migrago-office'),
        'awaiting_review' => __('في انتظار الرد/المراجعة', 'migrago-office'),
        'ready_pickup' => __('جاهز للاستلام', 'migrago-office'),
        'delivered' => __('تم التسليم', 'migrago-office'),
    ];
}


function migrago_office_workflow_stage_label(string $stage): string
{
    $stages = migrago_office_workflow_stages();

    return (string) ($stages[$stage] ?? $stage);
}

function migrago_office_create_client(array $data): int
{
    if (!is_user_logged_in()) {
        return 0;
    }

    if (!current_user_can('migrago_create_client') && !migrago_office_is_admin()) {
        return 0;
    }

    $full_name = sanitize_text_field($data['full_name'] ?? '');
    if ($full_name === '') {
        return 0;
    }

    $client_id = wp_insert_post([
        'post_type' => 'migrago_client',
        'post_status' => 'publish',
        'post_title' => $full_name,
        'post_author' => get_current_user_id(),
    ]);

    if (is_wp_error($client_id)) {
        return 0;
    }

    $file_number = 'MO-' . str_pad((string) $client_id, 6, '0', STR_PAD_LEFT);

    update_post_meta($client_id, '_migrago_file_number', $file_number);
    update_post_meta($client_id, '_migrago_phone', sanitize_text_field($data['phone'] ?? ''));
    update_post_meta($client_id, '_migrago_email', sanitize_email($data['email'] ?? ''));
    update_post_meta($client_id, '_migrago_passport_number', sanitize_text_field($data['passport_number'] ?? ''));
    update_post_meta($client_id, '_migrago_national_id', sanitize_text_field($data['national_id'] ?? ''));
    update_post_meta($client_id, '_migrago_service_type', sanitize_text_field($data['service_type'] ?? 'other'));
    update_post_meta($client_id, '_migrago_service_description', sanitize_textarea_field($data['service_description'] ?? ''));
    update_post_meta($client_id, '_migrago_birth_date', sanitize_text_field($data['birth_date'] ?? ''));
    update_post_meta($client_id, '_migrago_birth_place', sanitize_text_field($data['birth_place'] ?? ''));
    update_post_meta($client_id, '_migrago_address', sanitize_textarea_field($data['address'] ?? ''));
    update_post_meta($client_id, '_migrago_agreed_amount', (float) ($data['agreed_amount'] ?? 0));
    update_post_meta($client_id, '_migrago_paid_amount', (float) ($data['first_payment'] ?? 0));
    update_post_meta($client_id, '_migrago_payment_method', sanitize_text_field($data['payment_method'] ?? 'cash'));
    update_post_meta($client_id, '_migrago_workflow_stage', 'file_created');

    $contract_id = migrago_office_create_contract($client_id);
    if ($contract_id > 0) {
        update_post_meta($client_id, '_migrago_contract_id', $contract_id);
    }

    $first_payment = max(0.0, (float) ($data['first_payment'] ?? 0));
    $receipt_id = migrago_office_create_receipt($client_id, $first_payment, sanitize_text_field($data['payment_method'] ?? 'cash'));
    if ($receipt_id > 0) {
        update_post_meta($client_id, '_migrago_last_receipt_id', $receipt_id);
    }

    migrago_office_log_action('create_client', 'client', $client_id, [
        'file_number' => $file_number,
        'service_type' => sanitize_text_field($data['service_type'] ?? 'other'),
    ]);


    return $client_id;
}

function migrago_office_update_client(int $client_id, array $data): bool
{
    if ($client_id <= 0 || !is_user_logged_in()) {
        return false;
    }

    $can_manage = current_user_can('migrago_manage_clients') || current_user_can('migrago_manage_own_clients') || migrago_office_is_admin();
    if (!$can_manage) {
        return false;
    }

    $post = get_post($client_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'migrago_client') {
        return false;
    }

    if (!migrago_office_is_admin() && (int) $post->post_author !== get_current_user_id()) {
        return false;
    }

    $full_name = sanitize_text_field($data['full_name'] ?? $post->post_title);
    if ($full_name === '') {
        return false;
    }

    wp_update_post([
        'ID' => $client_id,
        'post_title' => $full_name,
    ]);

    update_post_meta($client_id, '_migrago_phone', sanitize_text_field($data['phone'] ?? ''));
    update_post_meta($client_id, '_migrago_email', sanitize_email($data['email'] ?? ''));
    update_post_meta($client_id, '_migrago_passport_number', sanitize_text_field($data['passport_number'] ?? ''));
    update_post_meta($client_id, '_migrago_national_id', sanitize_text_field($data['national_id'] ?? ''));
    update_post_meta($client_id, '_migrago_service_type', sanitize_text_field($data['service_type'] ?? 'other'));
    update_post_meta($client_id, '_migrago_service_description', sanitize_textarea_field($data['service_description'] ?? ''));
    update_post_meta($client_id, '_migrago_birth_date', sanitize_text_field($data['birth_date'] ?? ''));
    update_post_meta($client_id, '_migrago_birth_place', sanitize_text_field($data['birth_place'] ?? ''));
    update_post_meta($client_id, '_migrago_address', sanitize_textarea_field($data['address'] ?? ''));
    update_post_meta($client_id, '_migrago_agreed_amount', max(0.0, (float) ($data['agreed_amount'] ?? 0)));

    migrago_office_log_action('update_client', 'client', $client_id, ['full_name' => $full_name]);

    return true;
}

function migrago_office_search_clients(string $term): array
{
    if (!current_user_can('migrago_search_client') && !migrago_office_is_admin()) {
        return [];
    }

    $term = sanitize_text_field($term);
    $is_admin = migrago_office_is_admin();

    $base_args = [
        'post_type' => 'migrago_client',
        'post_status' => 'publish',
        'posts_per_page' => 60,
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    if (!$is_admin) {
        $base_args['author'] = get_current_user_id();
    }

    if ($term === '') {
        return get_posts($base_args);
    }

    $meta_args = $base_args;
    $meta_args['meta_query'] = [
        'relation' => 'OR',
        [
            'key' => '_migrago_passport_number',
            'value' => $term,
            'compare' => 'LIKE',
        ],
        [
            'key' => '_migrago_national_id',
            'value' => $term,
            'compare' => 'LIKE',
        ],
        [
            'key' => '_migrago_phone',
            'value' => $term,
            'compare' => 'LIKE',
        ],
        [
            'key' => '_migrago_file_number',
            'value' => $term,
            'compare' => 'LIKE',
        ],
        [
            'key' => '_migrago_email',
            'value' => $term,
            'compare' => 'LIKE',
        ],
    ];

    $title_args = $base_args;
    $title_args['s'] = $term;

    $meta_results = get_posts($meta_args);
    $title_results = get_posts($title_args);

    $merged = [];
    foreach (array_merge($meta_results, $title_results) as $client) {
        $merged[(int) $client->ID] = $client;
    }

    return array_values($merged);
}


function migrago_office_search_clients_for_member(string $term, int $user_id): array
{
    if ($user_id <= 0) {
        return [];
    }

    $term = sanitize_text_field($term);
    if ($term === '') {
        return get_posts([
            'post_type' => 'migrago_client',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'author' => $user_id,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    }

    $search_args = [
        'post_type' => 'migrago_client',
        'post_status' => 'publish',
        'posts_per_page' => 60,
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => '_migrago_passport_number',
                'value' => $term,
                'compare' => 'LIKE',
            ],
            [
                'key' => '_migrago_national_id',
                'value' => $term,
                'compare' => 'LIKE',
            ],
            [
                'key' => '_migrago_phone',
                'value' => $term,
                'compare' => 'LIKE',
            ],
        ],
    ];

    $meta_results = get_posts($search_args);

    $title_args = [
        'post_type' => 'migrago_client',
        'post_status' => 'publish',
        'posts_per_page' => 60,
        's' => $term,
    ];
    $title_results = get_posts($title_args);

    $merged = [];
    foreach (array_merge($meta_results, $title_results) as $client) {
        $merged[(int) $client->ID] = $client;
    }

    return array_values($merged);
}


function migrago_office_add_payment(int $client_id, float $amount, string $method): void
{
    if (!current_user_can('migrago_record_payment') && !migrago_office_is_admin()) {
        return;
    }

    $paid = (float) get_post_meta($client_id, '_migrago_paid_amount', true);
    update_post_meta($client_id, '_migrago_paid_amount', $paid + $amount);
    update_post_meta($client_id, '_migrago_payment_method', sanitize_text_field($method));
    update_post_meta($client_id, '_migrago_last_payment_date', current_time('mysql'));

    migrago_office_log_action('record_payment', 'client', $client_id, [
        'amount' => $amount,
        'method' => $method,
    ]);
}

function migrago_office_add_finance_entry(array $data): int
{
    if (!current_user_can('migrago_manage_accounting') && !migrago_office_is_admin()) {
        return 0;
    }

    $type = sanitize_text_field($data['entry_type'] ?? 'expense');
    if (!in_array($type, ['income', 'expense'], true)) {
        return 0;
    }

    $amount = (float) ($data['amount'] ?? 0);
    if ($amount <= 0) {
        return 0;
    }

    $category = sanitize_text_field($data['category'] ?? 'other_expense');
    if (!in_array($category, migrago_office_finance_categories($type), true)) {
        return 0;
    }

    $entry_id = wp_insert_post([
        'post_type' => 'migrago_finance_entry',
        'post_status' => 'publish',
        'post_title' => sanitize_text_field($data['title'] ?? ucfirst($type) . ' entry'),
        'post_author' => get_current_user_id(),
    ]);

    if (is_wp_error($entry_id)) {
        return 0;
    }

    update_post_meta($entry_id, '_migrago_entry_type', $type);
    update_post_meta($entry_id, '_migrago_amount', $amount);
    update_post_meta($entry_id, '_migrago_category', $category);
    update_post_meta($entry_id, '_migrago_entry_date', sanitize_text_field($data['entry_date'] ?? gmdate('Y-m-d')));
    update_post_meta($entry_id, '_migrago_note', sanitize_textarea_field($data['note'] ?? ''));

    migrago_office_log_action('add_finance_entry', 'finance_entry', (int) $entry_id, [
        'type' => $type,
        'amount' => $amount,
        'category' => $category,
    ]);

    return (int) $entry_id;
}

function migrago_office_finance_summary(): array
{
    $entries = get_posts([
        'post_type' => 'migrago_finance_entry',
        'posts_per_page' => 500,
        'post_status' => 'publish',
    ]);

    $income = 0.0;
    $expense = 0.0;
    $by_category = [];

    foreach ($entries as $entry) {
        $entry_type = (string) get_post_meta($entry->ID, '_migrago_entry_type', true);
        $amount = (float) get_post_meta($entry->ID, '_migrago_amount', true);
        $category = (string) get_post_meta($entry->ID, '_migrago_category', true);

        if (!isset($by_category[$category])) {
            $by_category[$category] = 0.0;
        }
        $by_category[$category] += $amount;

        if ($entry_type === 'income') {
            $income += $amount;
        } else {
            $expense += $amount;
        }
    }

    return [
        'income' => $income,
        'expense' => $expense,
        'net' => $income - $expense,
        'by_category' => $by_category,
        'count' => count($entries),
    ];
}

function migrago_office_generate_tracking_code(int $client_id): string
{
    $code = wp_generate_password(10, false, false);
    update_post_meta($client_id, '_migrago_tracking_code', $code);

    return $code;
}

function migrago_office_get_tracking_url(int $client_id): string
{
    $code = (string) get_post_meta($client_id, '_migrago_tracking_code', true);
    if ($code === '') {
        $code = migrago_office_generate_tracking_code($client_id);
    }

    return add_query_arg('code', rawurlencode($code), home_url('/track'));
}

function migrago_office_receipt_payload(int $client_id): array
{
    $file_number = (string) get_post_meta($client_id, '_migrago_file_number', true);

    return [
        'client_name' => get_the_title($client_id),
        'file_number' => $file_number,
        'paid_amount' => (float) get_post_meta($client_id, '_migrago_paid_amount', true),
        'remaining_amount' => migrago_office_remaining_amount($client_id),
        'payment_method' => (string) get_post_meta($client_id, '_migrago_payment_method', true),
        'tracking_url' => migrago_office_get_tracking_url($client_id),
    ];
}

function migrago_office_find_client_by_tracking_code(string $code): ?WP_Post
{
    $results = get_posts([
        'post_type' => 'migrago_client',
        'posts_per_page' => 1,
        'meta_key' => '_migrago_tracking_code',
        'meta_value' => sanitize_text_field($code),
    ]);

    return $results[0] ?? null;
}

function migrago_office_render_tracking(): string
{
    $code = sanitize_text_field($_GET['code'] ?? '');
    $client = $code !== '' ? migrago_office_find_client_by_tracking_code($code) : null;

    ob_start();
    include MIGRAGO_OFFICE_PATH . 'frontend/tracking-public.php';

    return (string) ob_get_clean();
}

function migrago_office_analytics_snapshot(): array
{
    $clients = wp_count_posts('migrago_client');

    return [
        'total_clients' => (int) ($clients->publish ?? 0),
    ];
}

function migrago_office_accounting_settings_defaults(): array
{
    return [
        'tva_enabled' => false,
        'tva_rate' => 20,
        'expense_types' => ['rent', 'internet', 'water', 'phone', 'purchases', 'salary', 'other_expense'],
    ];
}

function migrago_office_create_staff_user(array $data): int
{
    if (!current_user_can('migrago_manage_members') && !migrago_office_is_admin()) {
        return 0;
    }

    $email = sanitize_email($data['email'] ?? '');
    $username = sanitize_user($data['username'] ?? '');
    $password = (string) ($data['password'] ?? '');

    if ($email === '' || $username === '' || strlen($password) < 8 || username_exists($username) || email_exists($email)) {
        return 0;
    }

    $user_id = wp_insert_user([
        'user_login' => $username,
        'user_email' => $email,
        'user_pass' => $password,
        'display_name' => sanitize_text_field($data['full_name'] ?? $username),
        'role' => MIGRAGO_OFFICE_ROLE_MEMBER,
    ]);

    if (is_wp_error($user_id)) {
        return 0;
    }

    update_user_meta($user_id, '_migrago_phone', sanitize_text_field($data['phone'] ?? ''));
    update_user_meta($user_id, '_migrago_national_id', sanitize_text_field($data['national_id'] ?? ''));
    update_user_meta($user_id, '_migrago_photo_url', esc_url_raw($data['photo_url'] ?? ''));

    migrago_office_log_action('create_staff', 'user', (int) $user_id, [
        'username' => $username,
        'email' => $email,
    ]);

    return (int) $user_id;
}


function migrago_office_next_receipt_number(): int
{
    $next = (int) get_option('migrago_office_next_receipt_number', 1);
    update_option('migrago_office_next_receipt_number', $next + 1);

    return $next;
}

function migrago_office_create_receipt(int $client_id, float $amount, string $method): int
{
    $number = migrago_office_next_receipt_number();
    $receipt_id = wp_insert_post([
        'post_type' => 'migrago_receipt',
        'post_status' => 'publish',
        'post_title' => 'Receipt #' . $number,
        'post_author' => get_current_user_id(),
    ]);

    if (is_wp_error($receipt_id)) {
        return 0;
    }

    update_post_meta($receipt_id, '_migrago_receipt_number', $number);
    update_post_meta($receipt_id, '_migrago_client_id', $client_id);
    update_post_meta($receipt_id, '_migrago_amount', $amount);
    update_post_meta($receipt_id, '_migrago_method', sanitize_text_field($method));
    update_post_meta($receipt_id, '_migrago_date', current_time('mysql'));

    migrago_office_log_action('create_receipt', 'receipt', (int) $receipt_id, [
        'client_id' => $client_id,
        'amount' => $amount,
    ]);

    return (int) $receipt_id;
}

function migrago_office_get_receipt_data(int $receipt_id): array
{
    $client_id = (int) get_post_meta($receipt_id, '_migrago_client_id', true);
    $tracking_url = $client_id > 0 ? migrago_office_get_tracking_url($client_id) : '';
    $service_type = (string) get_post_meta($client_id, '_migrago_service_type', true);
    $stage_key = (string) get_post_meta($client_id, '_migrago_workflow_stage', true);

    return [
        'receipt_number' => (int) get_post_meta($receipt_id, '_migrago_receipt_number', true),
        'client_id' => $client_id,
        'client_name' => get_the_title($client_id),
        'cin' => (string) get_post_meta($client_id, '_migrago_national_id', true),
        'file_number' => (string) get_post_meta($client_id, '_migrago_file_number', true),
        'amount' => (float) get_post_meta($receipt_id, '_migrago_amount', true),
        'remaining' => migrago_office_remaining_amount($client_id),
        'method' => (string) get_post_meta($receipt_id, '_migrago_method', true),
        'date' => (string) get_post_meta($receipt_id, '_migrago_date', true),
        'service_type' => $service_type,
        'stage_label' => migrago_office_workflow_stage_label($stage_key),
        'tracking_url' => $tracking_url,
        'qr_image' => $tracking_url !== '' ? 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' . rawurlencode($tracking_url) : '',
        'company' => migrago_office_company_profile(),
    ];
}


function migrago_office_create_contract(int $client_id): int
{
    $client_name = get_the_title($client_id);
    $contract_id = wp_insert_post([
        'post_type' => 'migrago_contract',
        'post_status' => 'publish',
        'post_title' => 'Contract - ' . $client_name,
        'post_content' => migrago_office_get_contract_legal_content((string) get_post_meta($client_id, '_migrago_service_type', true)),
        'post_author' => get_current_user_id(),
    ]);

    if (is_wp_error($contract_id)) {
        return 0;
    }

    update_post_meta($contract_id, '_migrago_client_id', $client_id);
    migrago_office_log_action('create_contract', 'contract', (int) $contract_id, ['client_id' => $client_id]);

    return (int) $contract_id;
}

function migrago_office_get_client_file_number(int $client_id): string
{
    return (string) get_post_meta($client_id, '_migrago_file_number', true);
}


function migrago_office_get_client_receipt_url(int $client_id): string
{
    $receipt_id = (int) get_post_meta($client_id, '_migrago_last_receipt_id', true);
    if ($receipt_id <= 0) {
        $receipt_id = migrago_office_create_receipt($client_id, 0.0, (string) get_post_meta($client_id, '_migrago_payment_method', true));
        if ($receipt_id > 0) {
            update_post_meta($client_id, '_migrago_last_receipt_id', $receipt_id);
        }
    }

    return add_query_arg(['receipt_id' => (string) $receipt_id, 'client_id' => (string) $client_id], home_url('/receipt'));
}

function migrago_office_get_client_contract_url(int $client_id): string
{
    $contract_id = (int) get_post_meta($client_id, '_migrago_contract_id', true);
    if ($contract_id <= 0) {
        $contract_id = migrago_office_create_contract($client_id);
        if ($contract_id > 0) {
            update_post_meta($client_id, '_migrago_contract_id', $contract_id);
        }
    }

    return add_query_arg(['contract_id' => (string) $contract_id, 'client_id' => (string) $client_id], home_url('/contract'));
}


function migrago_office_build_contract_document_html(int $contract_id): string
{
    $data = migrago_office_get_contract_data($contract_id);
    if (empty($data)) {
        return '';
    }

    $contract_content_raw = (string) ($data['content'] ?? '');
    $contract_content = migrago_office_render_document_template($contract_content_raw, [
        'client_name' => esc_html((string) ($data['client_name'] ?? '')),
        'client_birth_date' => esc_html((string) ($data['client_birth_date'] ?? '................')),
        'client_birth_place' => esc_html((string) ($data['client_birth_place'] ?? '................')),
        'cin' => esc_html((string) ($data['cin'] ?? '')),
        'passport_number' => esc_html((string) ($data['passport_number'] ?? '')),
        'client_address' => esc_html((string) ($data['client_address'] ?? '................')),
        'service_type' => esc_html((string) ($data['service_type'] ?? '')),
        'agreed_amount' => esc_html(number_format((float) ($data['agreed_amount'] ?? 0), 2)),
        'file_number' => esc_html((string) ($data['file_number'] ?? '')),
        'contract_city' => esc_html((string) ($data['contract_city'] ?? '................')),
        'contract_date_pretty' => esc_html((string) ($data['contract_date_pretty'] ?? '................')),
    ]);

    $company = (array) ($data['company'] ?? migrago_office_company_profile());
    $vars = [
        'contract_number' => (string) ($data['contract_id'] ?? ''),
        'file_number' => (string) ($data['file_number'] ?? ''),
        'client_name' => esc_html((string) ($data['client_name'] ?? '')),
        'service_type' => esc_html((string) ($data['service_type'] ?? '')),
        'agreed_amount' => esc_html(number_format((float) ($data['agreed_amount'] ?? 0), 2)),
        'stage_label' => esc_html((string) ($data['stage_label'] ?? '')),
        'contract_content' => wp_kses_post($contract_content),
        'date' => esc_html((string) ($data['date'] ?? '')),
        'tracking_url' => esc_html((string) ($data['tracking_url'] ?? '')),
        'qr_image_tag' => !empty($data['qr_image']) ? '<img src="' . esc_url((string) $data['qr_image']) . '" alt="Tracking QR" width="140" height="140" />' : '',
        'company_name' => esc_html((string) ($company['name'] ?? '')),
        'company_description' => esc_html((string) ($company['description'] ?? '')),
        'company_address' => esc_html((string) ($company['address'] ?? '')),
        'company_phone' => esc_html((string) ($company['phone'] ?? '')),
        'company_ice' => esc_html((string) ($company['ice'] ?? '')),
    ];

    $template = migrago_office_get_full_contract_template((string) ($data['service_type'] ?? 'other'));
    $html = migrago_office_render_document_template($template, $vars);

    update_post_meta($contract_id, '_migrago_contract_document_html', $html);
    update_post_meta($contract_id, '_migrago_contract_document_updated_at', current_time('mysql'));

    return $html;
}

function migrago_office_get_contract_data(int $contract_id): array
{
    $client_id = (int) get_post_meta($contract_id, '_migrago_client_id', true);
    $tracking_url = $client_id > 0 ? migrago_office_get_tracking_url($client_id) : '';
    $stage_key = (string) get_post_meta($client_id, '_migrago_workflow_stage', true);

    $date_mysql = current_time('mysql');

    return [
        'contract_id' => $contract_id,
        'client_id' => $client_id,
        'client_name' => get_the_title($client_id),
        'cin' => (string) get_post_meta($client_id, '_migrago_national_id', true),
        'passport_number' => (string) get_post_meta($client_id, '_migrago_passport_number', true),
        'client_birth_date' => (string) get_post_meta($client_id, '_migrago_birth_date', true),
        'client_birth_place' => (string) get_post_meta($client_id, '_migrago_birth_place', true),
        'client_address' => (string) get_post_meta($client_id, '_migrago_address', true),
        'file_number' => (string) get_post_meta($client_id, '_migrago_file_number', true),
        'service_type' => (string) get_post_meta($client_id, '_migrago_service_type', true),
        'contract_type' => (string) get_post_meta($client_id, '_migrago_service_type', true),
        'agreed_amount' => (float) get_post_meta($client_id, '_migrago_agreed_amount', true),
        'content' => (string) get_post_field('post_content', $contract_id),
        'contract_city' => 'الدار البيضاء',
        'contract_date_pretty' => wp_date('d/m/Y', strtotime($date_mysql)),
        'stage_label' => migrago_office_workflow_stage_label($stage_key),
        'tracking_url' => $tracking_url,
        'qr_image' => $tracking_url !== '' ? 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' . rawurlencode($tracking_url) : '',
        'date' => $date_mysql,
        'company' => migrago_office_company_profile(),
    ];
}
