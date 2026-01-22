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
        $field_id = (string) $field->id;
        $mappings = CO360_DB::get_mappings_for_form_field( (int) $form['id'], $field_id );
        if ( empty( $mappings ) ) {
            return $result;
        }

        $course_ids = array_values( array_unique( array_map( 'intval', wp_list_pluck( $mappings, 'course_id' ) ) ) );
        if ( count( $course_ids ) > 1 ) {
            $result['is_valid'] = false;
            $result['message']  = __( 'Código duplicado. Contacta con soporte.', 'co360-attendance-codes' );
            error_log( 'CO360: múltiples course_id para el mismo form/field.' );
            return $result;
        }

        $code = co360_normalize_code( $value );
        if ( '' === $code ) {
            $result['is_valid'] = false;
            $result['message']  = __( 'Código incorrecto o ya utilizado', 'co360-attendance-codes' );
            return $result;
        }

        $project_ids = wp_list_pluck( $mappings, 'project_id' );
        $records     = CO360_DB::find_code_in_projects( $code, $project_ids );

        if ( empty( $records ) ) {
            $result['is_valid'] = false;
            $result['message']  = __( 'Código incorrecto o ya utilizado', 'co360-attendance-codes' );
            return $result;
        }

        if ( count( $records ) > 1 ) {
            $result['is_valid'] = false;
            $result['message']  = __( 'Código duplicado. Contacta con soporte.', 'co360-attendance-codes' );
            error_log( 'CO360: código duplicado en múltiples proyectos.' );
        }

        return $result;
    }

    public function handle_after_submission( $entry, $form ) {
        $mappings = CO360_DB::get_mappings_for_form( (int) $form['id'] );
        if ( empty( $mappings ) ) {
            return;
        }

        $grouped = array();
        foreach ( $mappings as $mapping ) {
            $grouped[ (string) $mapping->field_id ][] = $mapping;
        }

        foreach ( $grouped as $field_id => $group_mappings ) {
            if ( ! isset( $entry[ $field_id ] ) ) {
                continue;
            }

            $course_ids = array_values( array_unique( array_map( 'intval', wp_list_pluck( $group_mappings, 'course_id' ) ) ) );
            if ( count( $course_ids ) > 1 ) {
                error_log( 'CO360: múltiples course_id para el mismo form/field en after_submission.' );
                continue;
            }

            $code = co360_normalize_code( $entry[ $field_id ] );
            if ( '' === $code ) {
                continue;
            }

            $project_ids = wp_list_pluck( $group_mappings, 'project_id' );
            $records     = CO360_DB::find_code_in_projects( $code, $project_ids );

            if ( 1 !== count( $records ) ) {
                if ( count( $records ) > 1 ) {
                    error_log( 'CO360: código duplicado en múltiples proyectos en after_submission.' );
                }
                continue;
            }

            $record = $records[0];
            $updated = CO360_DB::increment_code_use( (int) $record->id );
            if ( ! $updated ) {
                continue;
            }

            $data = array(
                'project_id'  => (int) $record->project_id,
                'code_id'     => (int) $record->id,
                'code'        => $code,
                'user_id'     => get_current_user_id(),
                'gf_entry_id' => isset( $entry['id'] ) ? (int) $entry['id'] : 0,
                'course_id'   => isset( $course_ids[0] ) ? (int) $course_ids[0] : 0,
                'used_at'     => current_time( 'mysql' ),
                'ip'          => isset( $entry['ip'] ) ? sanitize_text_field( $entry['ip'] ) : '',
                'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            );

            CO360_DB::insert_use_log( $data );
        }
    }
}
