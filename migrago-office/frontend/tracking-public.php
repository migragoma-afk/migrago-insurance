<?php
$stages = migrago_office_workflow_stages();
?>
<div class="mo-track">
    <h1>Track your file</h1>
    <?php if (!$client) : ?>
        <p>Tracking code not found.</p>
    <?php else : ?>
        <?php $stage = (string) get_post_meta($client->ID, '_migrago_workflow_stage', true); ?>
        <p>Client: <?php echo esc_html($client->post_title); ?></p>
        <p>Service: <?php echo esc_html((string) get_post_meta($client->ID, '_migrago_service_type', true)); ?></p>
        <p>Current stage: <?php echo esc_html($stages[$stage] ?? $stage); ?></p>
        <ol>
            <?php foreach ($stages as $key => $label) : ?>
                <li class="<?php echo $key === $stage ? 'is-active' : ''; ?>"><?php echo esc_html($label); ?></li>
            <?php endforeach; ?>
        </ol>
        <?php if ($stage === 'ready_pickup') : ?>
            <p>مبروك، ملفك جاهز للاستلام من المكتب.</p>
        <?php elseif ($stage === 'delivered') : ?>
            <p>تم تسليم الملف بنجاح. شكراً لثقتكم.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
