<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CO360_CSV {
    public static function export_codes( $project_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'co360-attendance-codes' ) );
        }

        $project = CO360_DB::get_project( $project_id );
        if ( ! $project ) {
            wp_die( esc_html__( 'Proyecto no encontrado.', 'co360-attendance-codes' ) );
        }

        $codes = CO360_DB::get_codes( $project_id );

        $filename = 'co360-codes-' . sanitize_title( $project->name ) . '-' . gmdate( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'project_name', 'code', 'max_uses', 'uses_count', 'status', 'created_at' ) );

        foreach ( $codes as $code ) {
            $status = ( $code->uses_count >= $code->max_uses ) ? 'used' : 'unused';
            fputcsv(
                $output,
                array(
                    $project->name,
                    $code->code,
                    $code->max_uses,
                    $code->uses_count,
                    $status,
                    $code->created_at,
                )
            );
        }

        fclose( $output );
        exit;
    }
}
