<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WPR_YAI_Admin {
    public static function register() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_post_wpr_yai_settings', array( __CLASS__, 'save_settings' ) );
    }

    public static function menu() {
        add_menu_page( 'Yandex AI Studio', 'Yandex AI', 'manage_options', 'wpr-yai', array( __CLASS__, 'page' ), 'dashicons-superhero', 82 );
    }

    public static function assets( $hook ) {
        if ( 'toplevel_page_wpr-yai' !== $hook ) { return; }
        wp_enqueue_style( 'wpr-yai-admin', WPR_YAI_URL . 'assets/admin.css', array(), WPR_YAI_VERSION );
        wp_enqueue_script( 'wpr-yai-admin', WPR_YAI_URL . 'assets/admin.js', array(), WPR_YAI_VERSION, true );
        wp_add_inline_script( 'wpr-yai-admin', 'window.WPRYAI=' . wp_json_encode( array(
            'ajax' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wpr_yai_ajax' ),
        ) ) . ';', 'before' );
    }

    public static function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Недостаточно прав.' ); }
        check_admin_referer( 'wpr_yai_save_settings' );
        $folder = defined( 'WPR_YAI_FOLDER_ID' ) ? WPR_YAI_FOLDER_ID : ( isset( $_POST['folder'] ) ? sanitize_text_field( wp_unslash( $_POST['folder'] ) ) : '' );
        $model = defined( 'WPR_YAI_MODEL' ) ? WPR_YAI_MODEL : ( isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '' );
        if ( 'custom' === $model && ! defined( 'WPR_YAI_MODEL' ) ) {
            $model = isset( $_POST['model_custom'] ) ? sanitize_text_field( wp_unslash( $_POST['model_custom'] ) ) : '';
        }
        $key = isset( $_POST['api_key'] ) && is_string( $_POST['api_key'] ) ? trim( wp_unslash( $_POST['api_key'] ) ) : '';
        $url = admin_url( 'admin.php?page=wpr-yai&tab=settings' );
        if ( ! preg_match( '/^[a-zA-Z0-9_-]{5,128}$/', $folder ) || ! preg_match( '~^[a-zA-Z0-9][a-zA-Z0-9._/-]{1,150}$~', $model ) ) {
            wp_safe_redirect( add_query_arg( 'notice', 'invalid', $url ) ); exit;
        }
        // Валидация ключа до изменения настроек, чтобы не оставлять частичное состояние.
        $cipher = '';
        if ( '' !== $key && ! defined( 'WPR_YAI_API_KEY' ) ) {
            if ( strlen( $key ) > 1024 || preg_match( '/\s/', $key ) ) {
                wp_safe_redirect( add_query_arg( 'notice', 'invalid_key', $url ) ); exit;
            }
            $cipher = WPR_YAI_Core::encrypt_key( $key );
            if ( is_wp_error( $cipher ) ) { wp_safe_redirect( add_query_arg( 'notice', 'crypto', $url ) ); exit; }
        }
        // Константы wp-config.php имеют приоритет и не изменяются через админку.
        $settings = array( 'folder' => defined( 'WPR_YAI_FOLDER_ID' ) ? '' : $folder, 'model' => defined( 'WPR_YAI_MODEL' ) ? '' : $model );
        update_option( WPR_YAI_Core::OPTION, $settings, false );
        if ( '' !== $cipher ) { update_option( WPR_YAI_Core::KEY_OPTION, $cipher, false ); }
        elseif ( ! empty( $_POST['clear_key'] ) && ! defined( 'WPR_YAI_API_KEY' ) ) { delete_option( WPR_YAI_Core::KEY_OPTION ); }
        wp_safe_redirect( add_query_arg( 'notice', 'saved', $url ) ); exit;
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'assistant';
        if ( ! in_array( $tab, array( 'assistant', 'settings', 'help' ), true ) ) { $tab = 'assistant'; }
        $tabs = array( 'assistant' => 'ИИ-помощник', 'settings' => 'Подключение', 'help' => 'Как пользоваться' );
        ?>
        <div class="wrap wpr-yai-shell">
          <div class="wpr-yai-header"><div><h1>Yandex AI Studio <span>Assistant</span></h1><p>Работа с контентом WordPress через модели Яндекс AI Studio. Все изменения подтверждаются вручную.</p></div><a class="button button-secondary" href="<?php echo esc_url( WPR_YAI_DOCS ); ?>" target="_blank" rel="noopener noreferrer">Инструкция на ворд-пресс.рф ↗</a></div>
          <nav class="nav-tab-wrapper" aria-label="Разделы">
          <?php foreach ( $tabs as $slug => $name ) : ?>
          <a class="nav-tab <?php echo $slug === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wpr-yai&tab=' . $slug ) ); ?>"><?php echo esc_html( $name ); ?></a>
          <?php endforeach; ?></nav>
          <?php
          if ( 'settings' === $tab ) { self::settings_page(); }
          elseif ( 'help' === $tab ) { self::help_page(); }
          else { self::assistant_page(); }
          ?>
        </div>
        <?php
    }

    private static function assistant_page() {
        $configured = '' !== WPR_YAI_Core::api_key() && '' !== WPR_YAI_Core::settings()['folder'];
        $types = WPR_YAI_Contract::types();
        if ( ! $configured ) : ?>
          <div class="notice notice-warning inline"><p><strong>Нет подключения к Yandex AI Studio.</strong> Сначала укажите Folder ID и API-ключ в разделе <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpr-yai&tab=settings' ) ); ?>">«Подключение»</a>.</p></div>
        <?php endif; ?>
        <div class="wpr-yai-grid">
          <form id="wpr-yai-form" class="wpr-yai-panel">
            <h2>Задача для ИИ</h2>
            <label for="wpr-yai-mode"><strong>Действие</strong></label>
            <select id="wpr-yai-mode" name="mode">
              <option value="chat">Обсудить контент сайта</option>
              <option value="audit">Проанализировать материалы</option>
              <option value="generate">Подготовить новую запись</option>
              <option value="rewrite">Предложить редактирование записи</option>
            </select>
            <label for="wpr-yai-post-type"><strong>Тип контента (контракт)</strong></label>
            <select id="wpr-yai-post-type" name="post_type">
              <?php foreach ( $types as $type ) : ?><option value="<?php echo esc_attr( $type['name'] ); ?>"><?php echo esc_html( $type['label'] . ' (' . $type['name'] . ')' ); ?></option><?php endforeach; ?>
            </select>
            <button class="button" type="button" id="wpr-yai-contract-btn">Посмотреть контракт выбранного типа</button>
            <pre id="wpr-yai-contract-view" class="wpr-yai-code" hidden></pre>
            <p class="description">Схема CPT, таксономий, ACF и полей; до отправки задачи данные не передаются в AI Studio.</p>
            <label for="wpr-yai-target"><strong>Материал сайта (необязательно, кроме редактирования)</strong></label>
            <select id="wpr-yai-target" name="post_id"><option value="">Последние опубликованные / поиск по сайту</option>
              <?php
              $posts = get_posts( array( 'post_type' => array_keys( $types ), 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'posts_per_page' => 80, 'orderby' => 'modified', 'order' => 'DESC' ) );
              foreach ( $posts as $post ) { if ( current_user_can( 'edit_post', $post->ID ) ) { printf( '<option value="%d" data-type="%s">%s</option>', (int) $post->ID, esc_attr( $post->post_type ), esc_html( '#' . $post->ID . ' · ' . wp_trim_words( $post->post_title, 12 ) . ' [' . $post->post_status . ']' ) ); } }
              ?>
            </select>
            <label for="wpr-yai-search"><strong>Поиск материалов по сайту (опционально)</strong></label>
            <input id="wpr-yai-search" name="search" type="search" maxlength="100" placeholder="Например, настройка WordPress">
            <p class="description">Поиск и анализ ограничены выбранным типом записей (до 8–16 материалов). В контекст также входят описания полей ACF и зарегистрированной схемы.</p>
            <label for="wpr-yai-prompt"><strong>Твой запрос</strong></label>
            <textarea id="wpr-yai-prompt" name="prompt" rows="8" maxlength="4500" required placeholder="Например: найди дубли тем и предложи SEO-план; или подготовь подробную запись о ..."></textarea>
            <label class="wpr-yai-consent"><input type="checkbox" id="wpr-yai-consent" required> Согласен передать выбранный текст материалов и запрос в Yandex AI Studio для обработки.</label>
            <button class="button button-primary button-large" type="submit" <?php disabled( ! $configured ); ?>>Отправить модели</button>
            <span id="wpr-yai-spinner" class="spinner" aria-hidden="true"></span>
            <p class="description">Модель не меняет записи без вашего подтверждения. Переписка хранится в браузере только до перезагрузки страницы.</p>
          </form>
          <section class="wpr-yai-panel wpr-yai-result" aria-live="polite">
            <h2>Результат и предпросмотр</h2>
            <div id="wpr-yai-status" role="status">Выберите действие и отправьте задачу.</div>
            <div id="wpr-yai-output"></div>
          </section>
        </div>
        <?php
    }

    private static function settings_page() {
        $s = WPR_YAI_Core::settings();
        $notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
        $messages = array( 'saved' => 'Настройки сохранены.', 'invalid' => 'Некорректный Folder ID или идентификатор модели.', 'invalid_key' => 'Некорректный формат API-ключа.', 'crypto' => 'Не удалось зашифровать ключ. Используйте константу WPR_YAI_API_KEY в wp-config.php.' );
        if ( isset( $messages[ $notice ] ) ) { echo '<div class="notice ' . ( 'saved' === $notice ? 'notice-success' : 'notice-error' ) . ' inline"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>'; }
        ?>
        <div class="wpr-yai-panel wpr-yai-settings">
          <h2>Подключение к Yandex AI Studio</h2>
          <p>Нужны <strong>Folder ID</strong> каталога Yandex Cloud и <strong>секретное значение API-ключа</strong> с разрешением <code>yc.ai.foundationModels.execute</code>.</p>
          <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
            <input type="hidden" name="action" value="wpr_yai_settings">
            <?php wp_nonce_field( 'wpr_yai_save_settings' ); ?>
            <label for="wpr-yai-folder"><strong>Folder ID</strong></label>
            <input id="wpr-yai-folder" name="folder" class="regular-text" value="<?php echo esc_attr( $s['folder'] ); ?>" required <?php disabled( defined( 'WPR_YAI_FOLDER_ID' ) ); ?> placeholder="b1g...">
            <?php if ( defined( 'WPR_YAI_FOLDER_ID' ) ) : ?><p class="description">Значение задано через wp-config.php.</p><?php endif; ?>
            <label for="wpr-yai-key"><strong>API-ключ</strong></label>
            <input id="wpr-yai-key" name="api_key" class="regular-text" type="password" autocomplete="new-password" <?php disabled( defined( 'WPR_YAI_API_KEY' ) ); ?> placeholder="<?php echo esc_attr( WPR_YAI_Core::api_key() ? 'Ключ настроен — оставьте пустым, чтобы сохранить' : 'Вставьте секретный API-ключ' ); ?>">
            <p class="description">Ключ не выводится в браузер и хранится зашифрованным (AES-256-GCM), если настроен через админку. Также можно задать <code>WPR_YAI_API_KEY</code> в <code>wp-config.php</code>.</p>
            <?php if ( ! defined( 'WPR_YAI_API_KEY' ) ) : ?><label class="wpr-yai-inline"><input type="checkbox" name="clear_key" value="1"> Удалить сохранённый ключ</label><?php endif; ?>
            <label for="wpr-yai-model"><strong>Модель Yandex AI Studio</strong></label>
            <?php $models = WPR_YAI_Models::catalog(); $known = isset( $models[ $s['model'] ] ); ?>
            <select id="wpr-yai-model" name="model" <?php disabled( defined( 'WPR_YAI_MODEL' ) ); ?>>
              <?php foreach ( $models as $uri => $model ) : ?>
                <option value="<?php echo esc_attr( $uri ); ?>" data-description="<?php echo esc_attr( $model['purpose'] . ' Контекст: ' . $model['context'] . ( ! empty( $model['notice'] ) ? ' ' . $model['notice'] : '' ) ); ?>" <?php selected( $s['model'], $uri ); ?>><?php echo esc_html( $model['title'] ); ?></option>
              <?php endforeach; ?>
              <option value="custom" <?php selected( ! $known ); ?>>Другая модель (ввести вручную)</option>
            </select>
            <p id="wpr-yai-model-description" class="description"></p>
            <label id="wpr-yai-model-custom-wrap" for="wpr-yai-model-custom"><strong>ID другой модели</strong>
              <input id="wpr-yai-model-custom" name="model_custom" class="regular-text" value="<?php echo esc_attr( $known ? '' : $s['model'] ); ?>" <?php disabled( defined( 'WPR_YAI_MODEL' ) ); ?> placeholder="например: aliceai-llm/latest"></label>
            <p class="description">Выбирается URI после <code>gpt://&lt;folder-id&gt;/</code>. Указанные модели описаны на дату выпуска; доступность зависит от каталога и ключа.</p>
            <p><a href="<?php echo esc_url( WPR_YAI_Models::PRICE_URL ); ?>" target="_blank" rel="noopener noreferrer"><strong>Официальные тарифы Yandex AI Studio ↗</strong></a> ·
            <a href="<?php echo esc_url( WPR_YAI_Models::MODEL_URL ); ?>" target="_blank" rel="noopener noreferrer">Модели и сроки поддержки ↗</a></p>
            <?php submit_button( 'Сохранить настройки' ); ?>
          </form>
          <hr>
          <p><button class="button" id="wpr-yai-test" <?php disabled( '' === WPR_YAI_Core::api_key() || '' === $s['folder'] ); ?>>Проверить подключение (платный запрос к API)</button></p>
          <p id="wpr-yai-test-status" aria-live="polite"></p>
          <p><button class="button" type="button" id="wpr-yai-list-models" <?php disabled( '' === WPR_YAI_Core::api_key() || '' === $s['folder'] ); ?>>Показать доступные модели каталога</button></p>
          <pre id="wpr-yai-models-status" class="wpr-yai-code" aria-live="polite" style="white-space:pre-wrap"></pre>
          <p><a href="https://aistudio.yandex.ru/ru/docs/ai-studio/quickstart/" target="_blank" rel="noopener noreferrer">Официальный быстрый старт Яндекса ↗</a> · <a href="https://aistudio.yandex.ru/ru/docs/ai-studio/operations/get-api-key" target="_blank" rel="noopener noreferrer">Создание API-ключа ↗</a> · <a href="<?php echo esc_url( WPR_YAI_DOCS ); ?>" target="_blank" rel="noopener noreferrer">Пошаговая инструкция ворд-пресс.рф ↗</a></p>
        </div>
        <?php
    }

    private static function help_page() {
        ?>
        <div class="wpr-yai-panel wpr-yai-settings">
          <h2>Как начать</h2>
          <ol>
            <li>Создайте каталог в Yandex Cloud и API-ключ в AI Studio.</li>
            <li>В разделе «Подключение» заполните Folder ID и API-ключ, выберите модель, сохраните настройки и проверьте доступ.</li>
            <li>Откройте «ИИ-помощник», выберите тип записи (CPT), посмотрите его контракт и задайте задачу. Для редактирования выберите конкретный материал того же типа.</li>
            <li>Прочитайте ответ модели. Для записи или редактирования сначала проверьте все поля, затем нажмите «Сохранить черновик».</li>
            <li>Откройте черновик в WordPress, проверьте факты, ACF, таксономии, ссылки, Yoast SEO и только после этого публикуйте самостоятельно.</li>
          </ol>
          <p><strong>Безопасность:</strong> плагин не предоставляет публичный API, не публикует материалы автоматически и не предоставляет модели права менять настройки сайта. При редактировании опубликованной записи создаётся отдельный черновик. Для черновика или записи на модерации допускается обновление после подтверждения.</p>
          <p><strong>Контракты:</strong> автоматически доступны CPT, ACF и зарегистрированные метаполя. Сложные ACF-поля доступны как схема, но не записываются моделью. Точную логику сторонних PHP-шаблонов требуется описать отдельно.</p>
          <p><strong>Передача данных:</strong> только явно выбранные тексты или ограниченная выборка опубликованных статей передаются в Yandex AI Studio. Не передавайте персональные данные и секреты.</p>
          <p><a class="button button-primary" href="<?php echo esc_url( WPR_YAI_DOCS ); ?>" target="_blank" rel="noopener noreferrer">Полная инструкция на ворд-пресс.рф ↗</a></p>
        </div>
        <?php
    }
}
