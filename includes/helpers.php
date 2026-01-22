<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function co360_normalize_code( $code ) {
    return strtoupper( trim( (string) $code ) );
}

function co360_code_alphabet() {
    return 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
}

function co360_generate_code( $length ) {
    $alphabet = co360_code_alphabet();
    $max      = strlen( $alphabet ) - 1;
    $code     = '';

    for ( $i = 0; $i < $length; $i++ ) {
        $code .= $alphabet[ wp_rand( 0, $max ) ];
    }

    return $code;
}
