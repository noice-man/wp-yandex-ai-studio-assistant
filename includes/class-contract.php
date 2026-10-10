<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Public editorial schema only; never reads theme/plugin files, options or credentials. */
final class WPR_YAI_Contract {
    const MAX_FIELDS = 65;
    public static function ajax_contract() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Недостаточно прав.' ), 403 ); }
        check_ajax_referer( 'wpr_yai_ajax', 'nonce' );
        $type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post';
        $contract = self::schema( $type );
        if ( is_wp_error( $contract ) ) { wp_send_json_error( array( 'message' => $contract->get_error_message() ), 400 ); }
        wp_send_json_success( array( 'contract' => $contract ) );
    }

    public static function types() {
        $out = array();
        foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type => $obj ) {
            if ( 'attachment' === $type ) { continue; }
            $cap = isset( $obj->cap->edit_posts ) ? $obj->cap->edit_posts : 'edit_posts';
            if ( ! current_user_can( $cap ) ) { continue; }
            $out[ $type ] = array( 'name' => $type, 'label' => wp_strip_all_tags( $obj->labels->name ), 'supports' => array_values( get_all_post_type_supports( $type ) ? array_keys( get_all_post_type_supports( $type ) ) : array() ) );
        }
        return $out;
    }

    public static function valid_type( $type ) {
        $types = self::types();
        return isset( $types[ $type ] );
    }

    private static function safe_name( $name ) {
        return is_string( $name ) && preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,100}$/', $name ) && ! preg_match( '/password|secret|token|private|api.?key|credential|session|authorization|cookie|nonce|hash/i', $name );
    }

    private static function acf_groups( $post_type ) {
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) { return array(); }
        $results = array();
        $field_count = 0;
        foreach ( array_slice( (array) acf_get_field_groups(), 0, 100 ) as $group ) {
            $matched = false;
            // Conservative: only explicitly matched post_type rules are inferred.
            foreach ( (array) ( $group['location'] ?? array() ) as $or_group ) {
                $allowed = true;
                $has_type = false;
                foreach ( (array) $or_group as $rule ) {
                    if ( ( $rule['param'] ?? '' ) !== 'post_type' ) { $allowed = false; break; }
                    $has_type = true;
                    $op = $rule['operator'] ?? '==';
                    $value = $rule['value'] ?? '';
                    if ( '==' === $op && $value !== $post_type && '*' !== $value ) { $allowed = false; }
                    elseif ( '!=' === $op && $value === $post_type ) { $allowed = false; }
                    elseif ( ! in_array( $op, array( '==', '!=' ), true ) ) { $allowed = false; }
                }
                if ( $has_type && $allowed ) { $matched = true; break; }
            }
            if ( ! $matched || ( isset( $group['active'] ) && ! $group['active'] ) ) { continue; }
            $items = array();
            foreach ( (array) acf_get_fields( $group ) as $field ) {
                if ( ! is_array( $field ) || ! self::safe_name( $field['name'] ?? '' ) ) { continue; }
                $kind = (string) ( $field['type'] ?? '' );
                $writable = in_array( $kind, array( 'text', 'textarea', 'wysiwyg', 'number', 'true_false', 'select', 'radio' ), true );
                if ( $writable && in_array( $kind, array( 'select', 'radio' ), true ) && ! empty( $field['multiple'] ) ) { $writable = false; }
                $entry = array(
                    'name' => $field['name'], 'key' => (string) ( $field['key'] ?? '' ), 'label' => wp_strip_all_tags( (string) ( $field['label'] ?? $field['name'] ) ),
                    'type' => $kind, 'required' => ! empty( $field['required'] ), 'writable' => $writable,
                    'instructions' => wp_strip_all_tags( (string) ( $field['instructions'] ?? '' ) ),
                );
                if ( in_array( $kind, array( 'select', 'radio' ), true ) ) {
                    $entry['choices'] = array_slice( array_map( 'strval', array_keys( (array) ( $field['choices'] ?? array() ) ) ), 0, 40 );
                }
                if ( 'number' === $kind ) {
                    foreach ( array( 'min', 'max' ) as $limit ) {
                        if ( isset( $field[ $limit ] ) && '' !== $field[ $limit ] && is_numeric( $field[ $limit ] ) ) { $entry[ $limit ] = (float) $field[ $limit ]; }
                    }
                }
                $items[] = $entry;
                $field_count++;
                if ( $field_count >= self::MAX_FIELDS ) { break; }
            }
            if ( $items ) { $results[] = array( 'title' => wp_strip_all_tags( (string) ( $group['title'] ?? 'ACF' ) ), 'fields' => $items ); }
            if ( $field_count >= self::MAX_FIELDS || count( $results ) >= 12 ) { break; }
        }
        return $results;
    }

    public static function acf_writable( $post_type ) {
        $out = array();
        foreach ( self::acf_groups( $post_type ) as $group ) {
            foreach ( $group['fields'] as $field ) {
                if ( $field['writable'] ) { $out[ $field['name'] ] = $field; }
            }
        }
        return $out;
    }

    public static function schema( $post_type ) {
        if ( ! self::valid_type( $post_type ) ) { return new WP_Error( 'invalid_type', 'Тип записи недоступен для редактирования.' ); }
        $types = self::types();
        $obj = get_post_type_object( $post_type );
        $taxes = array();
        foreach ( get_object_taxonomies( $post_type, 'objects' ) as $tax => $tax_obj ) {
            if ( ! $tax_obj->show_ui ) { continue; }
            $terms = array();
            $known = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'number' => 30 ) );
            if ( ! is_wp_error( $known ) ) {
                foreach ( $known as $term ) { $terms[] = array( 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug ); }
            }
            $taxes[] = array( 'taxonomy' => $tax, 'label' => wp_strip_all_tags( $tax_obj->labels->name ), 'hierarchical' => (bool) $tax_obj->hierarchical, 'terms_sample' => $terms );
        }
        $registered = array();
        if ( function_exists( 'get_registered_meta_keys' ) ) {
            foreach ( (array) get_registered_meta_keys( 'post', $post_type ) as $key => $info ) {
                if ( count( $registered ) >= 35 ) { break; }
                if ( ! self::safe_name( $key ) || empty( $info['show_in_rest'] ) ) { continue; }
                $registered[] = array( 'key' => $key, 'type' => $info['type'] ?? 'string', 'writable' => false );
            }
        }
        $plugins = array();
        if ( function_exists( 'get_plugins' ) && function_exists( 'get_option' ) ) {
            $active = (array) get_option( 'active_plugins', array() );
            foreach ( array_slice( $active, 0, 40 ) as $file ) {
                // Only plugin display names. No paths, settings, source or environment are sent.
                $meta = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
                if ( ! empty( $meta['Name'] ) ) { $plugins[] = wp_strip_all_tags( $meta['Name'] ); }
            }
        }
        $templates = array();
        if ( function_exists( 'get_page_templates' ) ) {
            foreach ( array_slice( array_keys( (array) get_page_templates( null, $post_type ) ), 0, 20 ) as $template ) {
                $templates[] = sanitize_text_field( $template );
            }
        }
        $out = array(
            'post_type' => $post_type,
            'label' => $types[ $post_type ]['label'],
            'supports' => $types[ $post_type ]['supports'],
            'taxonomies' => $taxes,
            'acf_groups' => self::acf_groups( $post_type ),
            'registered_meta_readonly' => $registered,
            'theme' => array( 'name' => wp_get_theme()->get( 'Name' ), 'templates' => $templates ),
            'active_plugin_names' => $plugins,
            'editor_contract_note' => 'Только зарегистрированная схема: произвольный PHP шаблонов или логика сторонних плагинов автоматически не анализируются. Поля ACF сложных типов доступны для чтения схемы, но не для автозаписи.',
        );
        $extra = apply_filters( 'wpr_yai_extra_contract', array(), $post_type );
        if ( is_array( $extra ) ) {
            // Explicit opt-in by theme/plugin author; text-only bounded guidance.
            $out['developer_notes'] = substr( wp_strip_all_tags( wp_json_encode( $extra, JSON_UNESCAPED_UNICODE ) ), 0, 4000 );
        }
        return $out;
    }

    public static function acf_values( $post, $limit = 20 ) {
        $result = array();
        if ( ! function_exists( 'get_field' ) ) { return $result; }
        foreach ( self::acf_writable( $post->post_type ) as $name => $field ) {
            if ( count( $result ) >= $limit ) { break; }
            $value = get_field( $name, $post->ID, false );
            if ( is_bool( $value ) || is_numeric( $value ) ) { $result[ $name ] = $value; }
            elseif ( is_string( $value ) ) { $result[ $name ] = substr( wp_strip_all_tags( $value ), 0, 450 ); }
        }
        return $result;
    }

    /** Only assign existing terms of taxonomies registered for the selected CPT. */
    public static function normalize_terms( $terms, $post_type ) {
        if ( ! is_array( $terms ) ) { return new WP_Error( 'bad_tax', 'taxonomy_terms должен быть объектом.' ); }
        if ( count( $terms ) > 12 ) { return new WP_Error( 'bad_tax', 'Слишком много таксономий.' ); }
        $allowed = get_object_taxonomies( $post_type, 'objects' );
        $result = array();
        foreach ( $terms as $tax => $ids ) {
            if ( ! isset( $allowed[ $tax ] ) || ! $allowed[ $tax ]->show_ui || ! current_user_can( $allowed[ $tax ]->cap->assign_terms ) ) {
                return new WP_Error( 'bad_tax', 'Нет прав для назначения таксономии: ' . sanitize_key( $tax ) );
            }
            if ( ! is_array( $ids ) || count( $ids ) > 30 ) { return new WP_Error( 'bad_tax', 'Неверный набор терминов ' . sanitize_key( $tax ) ); }
            $validated = array();
            foreach ( $ids as $id ) {
                if ( ! is_int( $id ) && ! ( is_string( $id ) && ctype_digit( $id ) ) ) { return new WP_Error( 'bad_tax', 'Термину необходим числовой ID.' ); }
                $id = (int) $id;
                if ( $id < 1 || ! term_exists( $id, $tax ) ) { return new WP_Error( 'bad_tax', 'Термин не найден в таксономии ' . sanitize_key( $tax ) ); }
                $validated[] = $id;
            }
            $result[ $tax ] = array_values( array_unique( $validated ) );
        }
        return $result;
    }

    public static function normalize_fields( $values, $post_type ) {
        if ( ! is_array( $values ) ) { return new WP_Error( 'bad_acf', 'acf_fields должен быть JSON-объектом.' ); }
        $allowed = self::acf_writable( $post_type );
        $normalized = array();
        if ( count( $values ) > 60 ) { return new WP_Error( 'bad_acf', 'Слишком много полей ACF.' ); }
        foreach ( $values as $key => $value ) {
            if ( ! isset( $allowed[ $key ] ) ) { return new WP_Error( 'bad_acf', 'Недопустимое или недоступное для записи поле ACF: ' . sanitize_text_field( (string) $key ) ); }
            $f = $allowed[ $key ]; $kind = $f['type'];
            if ( 'true_false' === $kind ) {
                if ( ! in_array( $value, array( true, false, 1, 0, '1', '0' ), true ) ) { return new WP_Error( 'bad_acf', 'Ожидалось логическое значение для ' . $key ); }
                $normalized[ $key ] = (bool) $value;
            } elseif ( 'number' === $kind ) {
                if ( ! is_numeric( $value ) ) { return new WP_Error( 'bad_acf', 'Ожидалось число для ' . $key ); }
                $number = (float) $value;
                if ( ( isset( $f['min'] ) && $number < $f['min'] ) || ( isset( $f['max'] ) && $number > $f['max'] ) ) { return new WP_Error( 'bad_acf', 'Число вне допустимого диапазона: ' . $key ); }
                $normalized[ $key ] = $number;
            } elseif ( in_array( $kind, array( 'select', 'radio' ), true ) ) {
                if ( ! is_string( $value ) || ! in_array( $value, $f['choices'] ?? array(), true ) ) { return new WP_Error( 'bad_acf', 'Неизвестное значение списка ' . $key ); }
                $normalized[ $key ] = $value;
            } else {
                if ( ! is_string( $value ) || strlen( $value ) > 40000 ) { return new WP_Error( 'bad_acf', 'Неверная длина или тип поля ' . $key ); }
                $normalized[ $key ] = 'wysiwyg' === $kind ? wp_kses_post( $value ) : ( 'textarea' === $kind ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ) );
            }
        }
        return $normalized;
    }
}
