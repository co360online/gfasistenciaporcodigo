<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CO360_GF_Hooks {
    public function __construct() {
        add_filter( 'gform_field_validation', array( $this, 'validate_code' ), 10, 4 );
        add_action( 'gform_after_submission', array( $this, 'handle_after_submission' ), 10, 2 );
    }

    public function validate_code( $result, $value, $form, $field ) {
        $mapping = CO360_DB::get_mapping_for_form( (int) $form['id'] );
        if ( ! $mapping ) {
            return $result;
        }

        if ( (string) $field->id !== (string) $mapping->field_id ) {
            return $result;
        }

        $code = co360_normalize_code( $value );
        if ( '' === $code ) {
            $result['is_valid'] = false;
            $result['message']  = __( 'Código incorrecto o ya utilizado', 'co360-attendance-codes' );
            return $result;
        }

        $record = CO360_DB::get_code_for_project( (int) $mapping->project_id, $code );
        if ( ! $record || ! $record->is_active || $record->uses_count >= $record->max_uses ) {
            $result['is_valid'] = false;
            $result['message']  = __( 'Código incorrecto o ya utilizado', 'co360-attendance-codes' );
        }

        return $result;
    }

    public function handle_after_submission( $entry, $form ) {
        $mapping = CO360_DB::get_mapping_for_form( (int) $form['id'] );
        if ( ! $mapping ) {
            return;
        }

        $field_id = (string) $mapping->field_id;
        if ( ! isset( $entry[ $field_id ] ) ) {
            return;
        }

        $code = co360_normalize_code( $entry[ $field_id ] );
        if ( '' === $code ) {
            return;
        }

        $record = CO360_DB::get_code_for_project( (int) $mapping->project_id, $code );
        if ( ! $record || ! $record->is_active || $record->uses_count >= $record->max_uses ) {
            return;
        }

        $updated = CO360_DB::increment_code_use( (int) $record->id );
        if ( ! $updated ) {
            return;
        }

        $data = array(
            'project_id'  => (int) $mapping->project_id,
            'code_id'     => (int) $record->id,
            'code'        => $code,
            'user_id'     => get_current_user_id(),
            'gf_entry_id' => isset( $entry['id'] ) ? (int) $entry['id'] : 0,
            'used_at'     => current_time( 'mysql' ),
            'ip'          => isset( $entry['ip'] ) ? sanitize_text_field( $entry['ip'] ) : '',
            'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
        );

        CO360_DB::insert_use_log( $data );
    }
}
