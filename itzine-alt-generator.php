<?php
/**
 * Plugin Name: ITZine Alt Generator
 * Description: Автоматическая генерация Alt, Title и Description для изображений через OpenAI Vision
 * Version: 1.3.0
 * Author: Kuuuzya
 * Text Domain: itzine-alt-generator
 */

if (!defined('ABSPATH')) exit;

define('IAG_VERSION', '1.4.0');
define('IAG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IAG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IAG_OPTION_KEY', 'iag_settings');

// ─── Settings ─────────────────────────────────────────────────────────────────

add_action('admin_menu', function () {
    add_media_page('Alt Generator', 'Alt Generator', 'manage_options', 'itzine-alt-generator', 'iag_render_settings_page');
});

add_action('admin_init', function () {
    register_setting('iag_settings_group', IAG_OPTION_KEY, ['sanitize_callback' => 'iag_sanitize_settings']);
});

function iag_sanitize_settings($input) {
    $interval = (int)($input['cron_interval'] ?? 5);
    $interval = max(1, min(60, $interval));
    return [
        'api_key'       => sanitize_text_field($input['api_key'] ?? ''),
        'model'         => sanitize_text_field($input['model'] ?? 'gpt-4.1-nano'),
        'enabled'       => !empty($input['enabled']) ? 1 : 0,
        'overwrite_alt' => !empty($input['overwrite_alt']) ? 1 : 0,
        'cron_interval' => $interval,
    ];
}

function iag_get_settings() {
    return wp_parse_args(get_option(IAG_OPTION_KEY, []), [
        'api_key'       => '',
        'model'         => 'gpt-4.1-nano',
        'enabled'       => 1,
        'overwrite_alt' => 0,
        'cron_interval' => 5,
    ]);
}

// ─── Admin page ───────────────────────────────────────────────────────────────

function iag_render_settings_page() {
    $s = iag_get_settings();
    $models = [
        'gpt-4.1-nano' => 'GPT-4.1 Nano — быстрый, дешёвый',
        'gpt-4.1-mini' => 'GPT-4.1 Mini — баланс',
        'gpt-4o-mini'  => 'GPT-4o Mini',
        'gpt-4o'       => 'GPT-4o — мощный',
    ];
    ?>
    <style><?php include IAG_PLUGIN_DIR . 'assets/css/admin-style.css'; ?></style>

    <div class="iag-wrap">

        <header class="iag-topbar">
            <div class="iag-topbar__brand">
                <div class="iag-logo">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1L12.5 7.5H19L13.5 11.5L15.5 18L10 14L4.5 18L6.5 11.5L1 7.5H7.5L10 1Z"/></svg>
                </div>
                <div class="iag-topbar__titles">
                    <span class="iag-topbar__name">Alt Generator</span>
                    <span class="iag-topbar__meta">v<?= IAG_VERSION ?> · by Kuuuzya</span>
                </div>
            </div>
            <div class="iag-topbar__pill <?= $s['enabled'] ? 'iag-pill--on' : 'iag-pill--off' ?>">
                <span class="iag-pill__dot"></span>
                <?= $s['enabled'] ? 'Автогенерация активна' : 'Автогенерация выключена' ?>
            </div>
        </header>

        <?php settings_errors('iag_settings_group'); ?>

        <div class="iag-grid">

            <div class="iag-panel">
                <div class="iag-panel__title">Настройки</div>
                <form method="post" action="options.php">
                    <?php settings_fields('iag_settings_group'); ?>

                    <div class="iag-field">
                        <label class="iag-label" for="iag_api_key">OpenAI API Key</label>
                        <div class="iag-input-row">
                            <input type="password" id="iag_api_key"
                                name="<?= IAG_OPTION_KEY ?>[api_key]"
                                value="<?= esc_attr($s['api_key']) ?>"
                                class="iag-input" placeholder="sk-proj-..." autocomplete="off"/>
                            <button type="button" class="iag-icon-btn" onclick="iagToggleKey()" title="Показать">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <div class="iag-hint">platform.openai.com/api-keys</div>
                    </div>

                    <div class="iag-field">
                        <label class="iag-label" for="iag_model">Модель</label>
                        <div class="iag-select-wrap">
                            <select id="iag_model" name="<?= IAG_OPTION_KEY ?>[model]" class="iag-select">
                                <?php foreach ($models as $val => $label): ?>
                                    <option value="<?= esc_attr($val) ?>" <?= selected($s['model'], $val, false) ?>><?= esc_html($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="iag-select-arrow" width="12" height="12" viewBox="0 0 12 8" fill="none"><path d="M1 1l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </div>
                    </div>

                    <div class="iag-field">
                        <div class="iag-toggle-item">
                            <div>
                                <div class="iag-label" style="margin-bottom:2px">Автогенерация (крон)</div>
                                <div class="iag-hint">Обрабатывает изображения без description — значит ещё не проходили</div>
                            </div>
                            <label class="iag-switch">
                                <input type="hidden" name="<?= IAG_OPTION_KEY ?>[enabled]" value="0">
                                <input type="checkbox" name="<?= IAG_OPTION_KEY ?>[enabled]" value="1" <?= checked($s['enabled'], 1, false) ?>>
                                <span class="iag-switch__track"></span>
                            </label>
                        </div>
                        <div class="iag-toggle-item">
                            <div>
                                <div class="iag-label" style="margin-bottom:2px">Перезаписывать alt</div>
                                <div class="iag-hint">Если выключено — пропускает фото у которых alt уже есть</div>
                            </div>
                            <label class="iag-switch">
                                <input type="hidden" name="<?= IAG_OPTION_KEY ?>[overwrite_alt]" value="0">
                                <input type="checkbox" name="<?= IAG_OPTION_KEY ?>[overwrite_alt]" value="1" <?= checked($s['overwrite_alt'], 1, false) ?>>
                                <span class="iag-switch__track"></span>
                            </label>
                        </div>
                    </div>

                    <div class="iag-field">
                        <label class="iag-label" for="iag_cron_interval">Интервал крона: <span id="iag-interval-val"><?= (int)$s['cron_interval'] ?></span> мин</label>
                        <input type="range" id="iag_cron_interval"
                            name="<?= IAG_OPTION_KEY ?>[cron_interval]"
                            min="1" max="60" step="1"
                            value="<?= (int)$s['cron_interval'] ?>"
                            class="iag-range"
                            oninput="document.getElementById('iag-interval-val').textContent=this.value">
                        <div class="iag-range-labels"><span>1 мин</span><span>30 мин</span><span>60 мин</span></div>
                        <div class="iag-hint">Изменение вступит в силу после сохранения и реактивации крона</div>
                    </div>

                    <div class="iag-actions">
                        <?php submit_button('Сохранить', 'primary', 'submit', false, ['class' => 'iag-btn iag-btn--primary']); ?>
                        <button type="button" class="iag-btn iag-btn--ghost" id="iag-test-btn">Тест API</button>
                    </div>
                    <div id="iag-test-result" style="display:none" class="iag-notice"></div>
                </form>
            </div>

            <div class="iag-sidebar">

                <div class="iag-panel">
                    <div class="iag-panel__title">Пакетная генерация</div>
                    <div class="iag-hint" style="margin-bottom:16px">Обрабатывает фото без description. Новые — в приоритете.</div>

                    <div class="iag-field">
                        <label class="iag-label" for="iag-batch-limit">Лимит</label>
                        <input type="number" id="iag-batch-limit" class="iag-input" value="50" min="1" max="1000" style="width:120px">
                    </div>

                    <button type="button" class="iag-btn iag-btn--primary iag-btn--full" id="iag-batch-btn">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Запустить
                    </button>

                    <div id="iag-batch-progress" style="display:none;margin-top:14px">
                        <div class="iag-bar"><div class="iag-bar__fill" id="iag-bar-fill"></div></div>
                        <div class="iag-bar__label" id="iag-bar-label">Подготовка...</div>
                    </div>
                    <div id="iag-batch-log" class="iag-log" style="display:none"></div>
                </div>

                <div class="iag-panel">
                    <div class="iag-panel__title">Статистика</div>
                    <div id="iag-stats">
                        <button type="button" class="iag-btn iag-btn--ghost iag-btn--full" id="iag-stats-btn">Загрузить</button>
                    </div>
                </div>

            </div>
        </div>

        <div class="iag-footer">ITZine Alt Generator <?= IAG_VERSION ?> · by Kuuuzya · OpenAI Vision API</div>
    </div>

    <script><?php include IAG_PLUGIN_DIR . 'assets/js/admin-script.js'; ?></script>
    <?php
}

// ─── Cron: dynamic interval from settings ────────────────────────────────────

add_filter('cron_schedules', function ($schedules) {
    $s        = iag_get_settings();
    $interval = max(1, (int)$s['cron_interval']) * 60;
    $schedules['iag_custom_interval'] = [
        'interval' => $interval,
        'display'  => 'IAG every ' . $s['cron_interval'] . ' min',
    ];
    return $schedules;
});

// Перепланируем крон при сохранении настроек
add_action('update_option_' . IAG_OPTION_KEY, function () {
    wp_clear_scheduled_hook('iag_cron_generate');
    $s = iag_get_settings();
    if ($s['enabled']) {
        wp_schedule_event(time(), 'iag_custom_interval', 'iag_cron_generate');
    }
});

register_activation_hook(__FILE__, function () {
    $s = iag_get_settings();
    if (!wp_next_scheduled('iag_cron_generate') && $s['enabled']) {
        wp_schedule_event(time(), 'iag_custom_interval', 'iag_cron_generate');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('iag_cron_generate');
});

// ─── Cron job: process images without description ─────────────────────────────

add_action('iag_cron_generate', function () {
    $s = iag_get_settings();
    if (!$s['enabled'] || !$s['api_key']) return;

    global $wpdb;

    // Берём изображения у которых не заполнено хотя бы одно из трёх полей
    $rows = $wpdb->get_results("
        SELECT p.ID, p.post_parent
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
        WHERE p.post_type = 'attachment'
        AND p.post_mime_type LIKE 'image/%'
        AND p.post_status = 'inherit'
        AND NOT (
            (p.post_title   != '' AND p.post_title   IS NOT NULL) AND
            (p.post_content != '' AND p.post_content IS NOT NULL) AND
            (pm.meta_value  != '' AND pm.meta_value  IS NOT NULL AND LENGTH(pm.meta_value) >= 15)
        )
        ORDER BY p.ID DESC
        LIMIT 10
    ");

    if (empty($rows)) return;

    foreach ($rows as $row) {
        $result = iag_generate_for_attachment((int)$row->ID);

        // Если успешно и есть родительский пост — обновляем alt в post_content поста
        if (!isset($result['error']) && $row->post_parent) {
            iag_sync_alt_in_post($row->post_parent, (int)$row->ID, $result['data']['alt']);
        }
    }
});

// ─── Sync alt in post_content ─────────────────────────────────────────────────

function iag_sync_alt_in_post($post_id, $att_id, $new_alt) {
    $post = get_post($post_id);
    if (!$post || empty($post->post_content)) return;

    $new_content = preg_replace_callback(
        '/<img[^>]*>/i',
        function ($m) use ($att_id, $new_alt) {
            $tag = $m[0];
            if (!preg_match('/\bwp-image-' . $att_id . '\b/', $tag)) return $tag;
            return preg_replace('/\balt="[^"]*"/', 'alt="' . esc_attr($new_alt) . '"', $tag);
        },
        $post->post_content
    );

    if ($new_content !== $post->post_content) {
        // wp_update_post сбросит modified date — используем прямой запрос
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            ['post_content' => $new_content],
            ['ID' => $post_id]
        );
        clean_post_cache($post_id);
    }
}

// ─── Core generation ─────────────────────────────────────────────────────────

function iag_generate_for_attachment($attachment_id) {
    $s = iag_get_settings();
    if (!$s['api_key']) return ['error' => 'Нет API ключа'];

    $post = get_post($attachment_id);
    if (!$post) return ['error' => 'Вложение не найдено'];

    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path)) return ['error' => 'Файл не найден'];

    $mime = mime_content_type($file_path);

    if (in_array($mime, ['image/avif', 'image/webp'])) {
        $blob = iag_convert_to_jpeg($file_path);
        if (!$blob) return ['error' => 'Не удалось конвертировать ' . $mime];
        $b64  = base64_encode($blob);
        $mime = 'image/jpeg';
    } else {
        $blob = file_get_contents($file_path);
        if (!$blob) return ['error' => 'Не удалось прочитать файл'];
        $b64  = base64_encode($blob);
    }

    // Заголовок родительского поста
    $post_title = '';
    if ($post->post_parent) {
        $parent = get_post($post->post_parent);
        if ($parent) $post_title = $parent->post_title;
    }

    $response = iag_call_openai($s['api_key'], $s['model'], $b64, $mime, iag_build_prompt($post_title));
    if (isset($response['error'])) return $response;

    update_post_meta($attachment_id, '_wp_attachment_image_alt', $response['alt']);
    wp_update_post([
        'ID'           => $attachment_id,
        'post_title'   => $response['title'],
        'post_content' => $response['description'],
    ]);

    return ['success' => true, 'data' => $response];
}

function iag_convert_to_jpeg($file_path) {
    if (class_exists('Imagick')) {
        try { $img = new Imagick($file_path); $img->setImageFormat('jpeg'); $img->setImageCompressionQuality(85); return $img->getImageBlob(); } catch (Exception $e) {}
    }
    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring(file_get_contents($file_path));
        if ($img) { ob_start(); imagejpeg($img, null, 85); imagedestroy($img); return ob_get_clean(); }
    }
    return null;
}

function iag_build_prompt($post_title = '') {
    $no_bad = 'Never start with or use: "На изображении изображён/а/о", "На фото изображён", "На картинке", "Изображение показывает", "Показан". Start directly with the subject.';

    if ($post_title) {
        return 'Image from article: "' . $post_title . '". Include device/product name in fields if relevant.
Always respond in Russian. Return ONLY valid JSON:
{"alt":"5-8 Russian words. Include product name from article title. Describe exactly what is shown. No guessing words. No punctuation at end.","title":"8-12 Russian words, different from alt. No punctuation at end.","description":"1-2 Russian sentences. ' . $no_bad . ' No punctuation at end."}';
    }

    return 'Tech image. Always respond in Russian. Return ONLY valid JSON:
{"alt":"5-8 Russian words. Describe exactly: device, component, interface. No guessing. No punctuation at end.","title":"8-12 Russian words, different from alt. No punctuation at end.","description":"1-2 Russian sentences. ' . $no_bad . ' No punctuation at end."}';
}

function iag_call_openai($api_key, $model, $b64, $mime, $prompt) {
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'timeout' => 30,
        'headers' => ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'],
        'body'    => wp_json_encode([
            'model'      => $model,
            'max_tokens' => 300,
            'messages'   => [['role' => 'user', 'content' => [
                ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . $b64, 'detail' => 'low']],
                ['type' => 'text', 'text' => $prompt],
            ]]],
        ]),
    ]);

    if (is_wp_error($response)) return ['error' => $response->get_error_message()];

    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($code !== 200) return ['error' => $data['error']['message'] ?? 'OpenAI error ' . $code];

    $raw    = trim(preg_replace('/^```json\s*|```$/m', '', trim($data['choices'][0]['message']['content'] ?? '')));
    $parsed = json_decode($raw, true);
    if (!$parsed) return ['error' => 'JSON parse error: ' . substr($raw, 0, 150)];

    return [
        'alt'         => rtrim(trim($parsed['alt'] ?? ''), '.'),
        'title'       => rtrim(trim($parsed['title'] ?? ''), '.'),
        'description' => rtrim(trim($parsed['description'] ?? ''), '.'),
    ];
}

// ─── AJAX handlers ────────────────────────────────────────────────────────────

add_action('wp_ajax_iag_test_connection', function () {
    check_ajax_referer('iag_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    if (!$api_key) wp_send_json_error('Введи API ключ');
    $r = wp_remote_get('https://api.openai.com/v1/models', ['timeout' => 10, 'headers' => ['Authorization' => 'Bearer ' . $api_key]]);
    if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
    $code = wp_remote_retrieve_response_code($r);
    if ($code === 200) wp_send_json_success('Соединение успешно ✓');
    $body = json_decode(wp_remote_retrieve_body($r), true);
    wp_send_json_error($body['error']['message'] ?? 'Ошибка ' . $code);
});

add_action('wp_ajax_iag_get_stats', function () {
    check_ajax_referer('iag_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    global $wpdb;
    $total    = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'");
    $with_alt = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='_wp_attachment_image_alt' AND LENGTH(pm.meta_value)>=15 AND p.post_mime_type LIKE 'image/%'");
    $no_alt   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id=p.ID AND pm.meta_key='_wp_attachment_image_alt' AND pm.meta_value!='')");
    $bad_alt  = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='_wp_attachment_image_alt' AND LENGTH(pm.meta_value)<15 AND pm.meta_value!='' AND p.post_mime_type LIKE 'image/%'");
    wp_send_json_success(['total' => $total, 'with_alt' => $with_alt, 'no_alt' => $no_alt, 'bad_alt' => $bad_alt, 'percent' => $total > 0 ? round($with_alt / $total * 100) : 0]);
});

add_action('wp_ajax_iag_batch_next', function () {
    check_ajax_referer('iag_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $s = iag_get_settings();
    if (!$s['api_key']) wp_send_json_error('Нет API ключа');
    global $wpdb;
    $row = $wpdb->get_row("
        SELECT p.ID, p.post_parent FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
        WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' AND p.post_status='inherit'
        AND NOT (
            (p.post_title   != '' AND p.post_title   IS NOT NULL) AND
            (p.post_content != '' AND p.post_content IS NOT NULL) AND
            (pm.meta_value  != '' AND pm.meta_value  IS NOT NULL AND LENGTH(pm.meta_value) >= 15)
        )
        ORDER BY p.ID DESC LIMIT 1
    ");
    if (!$row) { wp_send_json_success(['done' => true]); }
    $result = iag_generate_for_attachment((int)$row->ID);
    if (isset($result['error'])) { wp_send_json_success(['done' => false, 'id' => $row->ID, 'error' => $result['error']]); }
    if ($row->post_parent) {
        iag_sync_alt_in_post((int)$row->post_parent, (int)$row->ID, $result['data']['alt']);
    }
    wp_send_json_success(['done' => false, 'id' => $row->ID, 'alt' => $result['data']['alt'], 'url' => admin_url('post.php?post=' . $row->ID . '&action=edit')]);
});

add_action('wp_ajax_iag_generate_single', function () {
    check_ajax_referer('iag_nonce', 'nonce');
    if (!current_user_can('upload_files')) wp_die();
    $id = (int)($_POST['attachment_id'] ?? 0);
    if (!$id) wp_send_json_error('Нет ID');
    $result = iag_generate_for_attachment($id);
    if (isset($result['error'])) wp_send_json_error($result['error']);
    $post = get_post($id);
    if ($post->post_parent) {
        iag_sync_alt_in_post((int)$post->post_parent, $id, $result['data']['alt']);
    }
    wp_send_json_success($result['data']);
});

// ─── Media library button ─────────────────────────────────────────────────────

add_filter('attachment_fields_to_edit', function ($fields, $post) {
    $nonce = wp_create_nonce('iag_nonce');
    $id    = $post->ID;
    $fields['iag_generate'] = ['label' => 'Alt Generator', 'input' => 'html', 'html' =>
        '<button type="button" class="button iag-media-btn" data-id="' . $id . '" data-nonce="' . $nonce . '">✦ Сгенерировать</button>
        <span class="iag-media-status" style="margin-left:8px;font-size:12px"></span>
        <script>(function(){
            var b=document.querySelector(".iag-media-btn[data-id=\"' . $id . '\"]");
            if(!b)return;
            b.addEventListener("click",function(){
                b.disabled=true;b.textContent="Генерирую...";
                var s=b.nextElementSibling;
                fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({action:"iag_generate_single",nonce:b.dataset.nonce,attachment_id:b.dataset.id})})
                .then(r=>r.json()).then(d=>{
                    s.textContent=d.success?"✓ "+d.data.alt:"✗ "+d.data;
                    s.style.color=d.success?"#1a7f37":"#cf222e";
                    b.disabled=false;b.textContent="✦ Сгенерировать";
                });
            });
        })();</script>'];
    return $fields;
}, 10, 2);

add_action('admin_head', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'media_page_itzine-alt-generator') return;
    echo '<script>var iagNonce="' . wp_create_nonce('iag_nonce') . '";</script>';
});
