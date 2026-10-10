<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Model identifiers: Yandex AI Studio public model catalog, checked 2026-10-10. */
final class WPR_YAI_Models {
    const PRICE_URL = 'https://aistudio.yandex.ru/ru/docs/ai-studio/pricing';
    const MODEL_URL = 'https://aistudio.yandex.ru/ru/docs/ai-studio/concepts/generation/models';
    public static function catalog() {
        return array(
            'aliceai-llm-flash' => array( 'title' => 'Alice AI LLM Flash', 'purpose' => 'Быстрые ответы, метаописания и массовая редактура; экономный вариант.', 'context' => '64k' ),
            'aliceai-llm' => array( 'title' => 'Alice AI LLM', 'purpose' => 'Развёрнутые материалы, сложные инструкции и большой контекст.', 'context' => '128k' ),
            'yandexgpt-5.1' => array( 'title' => 'YandexGPT Pro 5.1', 'purpose' => 'Универсальная русскоязычная редактура, анализ контента, SEO-структура.', 'context' => '32k' ),
            'yandexgpt-5-lite' => array( 'title' => 'YandexGPT Lite 5', 'purpose' => 'Короткие тексты и простые задачи при умеренном расходе токенов.', 'context' => '32k' ),
            'qwen3.6-35b-a3b' => array( 'title' => 'Qwen3.6 35B', 'purpose' => 'Сложные структуры ACF/CPT, JSON и технический анализ.', 'context' => '256k' ),
            'deepseek-v4-flash' => array( 'title' => 'DeepSeek V4 Flash', 'purpose' => 'Большой контекст и многошаговый анализ структур данных.', 'context' => '1M' ),
            'deepseek-v4.1-flash' => array( 'title' => 'DeepSeek V4.1 Flash', 'purpose' => 'Актуальная высококонтекстная модель для анализа и генерации.', 'context' => '1M' ),
            'gpt-oss-120b' => array( 'title' => 'GPT-OSS 120B', 'purpose' => 'Сложные инструкции; заявлен вывод из эксплуатации 30.10.2026.', 'context' => '128k', 'notice' => 'Срок поддержки заканчивается 30.10.2026.' ),
        );
    }
    public static function ajax_models() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Недостаточно прав.' ), 403 ); }
        check_ajax_referer( 'wpr_yai_ajax', 'nonce' );
        $s = WPR_YAI_Core::settings(); $key = WPR_YAI_Core::api_key();
        if ( '' === $key || '' === $s['folder'] ) { wp_send_json_error( array( 'message' => 'Настройте API-ключ и Folder ID.' ), 400 ); }
        $response = wp_remote_get( 'https://ai.api.cloud.yandex.net/v1/models', array(
            'timeout' => 25, 'redirection' => 0,
            'headers' => array( 'Authorization' => 'Api-Key ' . $key, 'x-project' => $s['folder'], 'OpenAI-Project' => $s['folder'] ),
        ) );
        if ( is_wp_error( $response ) ) { wp_send_json_error( array( 'message' => 'Ошибка подключения к каталогу моделей.' ), 502 ); }
        if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            wp_send_json_error( array( 'message' => 'Список моделей недоступен: HTTP ' . (int) wp_remote_retrieve_response_code( $response ) . '. Проверьте область ключа yc.ai.models.viewer.' ), 502 );
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $items = array();
        if ( is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ) {
            foreach ( array_slice( $data['data'], 0, 200 ) as $entry ) {
                $uri = (string) ( $entry['id'] ?? '' );
                if ( 0 === strpos( $uri, 'gpt://' . $s['folder'] . '/' ) ) {
                    $items[] = substr( $uri, strlen( 'gpt://' . $s['folder'] . '/' ) );
                }
            }
        }
        wp_send_json_success( array( 'models' => array_values( array_unique( $items ) ), 'count' => count( $items ) ) );
    }
}
