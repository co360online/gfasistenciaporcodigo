<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CO360_DB {
    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'co360_' . $name;
    }

    public static function activate() {
        self::create_tables();
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $projects = self::table( 'projects' );
        $codes    = self::table( 'codes' );
        $uses     = self::table( 'uses' );
        $maps     = self::table( 'gf_mappings' );

        $sql = array();

        $sql[] = "CREATE TABLE {$projects} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$codes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(50) NOT NULL,
            max_uses INT NOT NULL DEFAULT 1,
            uses_count INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY project_id (project_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$uses} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            code_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(50) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            gf_entry_id BIGINT UNSIGNED NULL,
            course_id INT NULL DEFAULT 0,
            used_at DATETIME NOT NULL,
            ip VARCHAR(100) NULL,
            user_agent TEXT NULL,
            PRIMARY KEY  (id),
            KEY project_id (project_id),
            KEY code_id (code_id)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$maps} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id INT NOT NULL,
            field_id VARCHAR(50) NOT NULL,
            course_id INT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY project_id (project_id)
        ) {$charset};";

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }

        self::maybe_add_column( $maps, 'course_id', 'INT NULL DEFAULT 0' );
        self::maybe_add_column( $uses, 'course_id', 'INT NULL DEFAULT 0' );
    }

    private static function maybe_add_column( $table, $column, $definition ) {
        global $wpdb;
        $existing = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
        if ( ! $existing ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}" );
        }
    }

    public static function insert_project( $name, $description ) {
        global $wpdb;
        $table = self::table( 'projects' );
        $now   = current_time( 'mysql' );

        $wpdb->insert(
            $table,
            array(
                'name'        => $name,
                'description' => $description,
                'status'      => 'active',
                'created_at'  => $now,
                'updated_at'  => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }

    public static function update_project( $id, $name, $description, $status ) {
        global $wpdb;
        $table = self::table( 'projects' );
        $wpdb->update(
            $table,
            array(
                'name'        => $name,
                'description' => $description,
                'status'      => $status,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function toggle_project_status( $id ) {
        global $wpdb;
        $table  = self::table( 'projects' );
        $status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $id ) );
        if ( ! $status ) {
            return;
        }
        $new_status = ( 'active' === $status ) ? 'archived' : 'active';
        $wpdb->update(
            $table,
            array(
                'status'     => $new_status,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function get_project( $id ) {
        global $wpdb;
        $table = self::table( 'projects' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    public static function get_projects() {
        global $wpdb;
        $table = self::table( 'projects' );
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
    }

    public static function get_project_stats( $project_id ) {
        global $wpdb;
        $codes = self::table( 'codes' );
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN uses_count >= max_uses THEN 1 ELSE 0 END) AS used,
                    SUM(CASE WHEN uses_count < max_uses THEN 1 ELSE 0 END) AS available
                FROM {$codes}
                WHERE project_id = %d",
                $project_id
            )
        );
    }

    public static function get_codes( $project_id = 0 ) {
        global $wpdb;
        $codes = self::table( 'codes' );
        $uses  = self::table( 'uses' );
        $users = $wpdb->users;

        $select = "SELECT
            codes.*,
            last_uses.last_used_at,
            last_uses.last_user_id,
            users.user_email";

        $from = " FROM {$codes} AS codes
            LEFT JOIN (
                /* Last use per code_id: pick MAX(used_at) and its corresponding user_id. */
                SELECT
                    uses.code_id,
                    uses.user_id AS last_user_id,
                    uses.used_at AS last_used_at
                FROM {$uses} AS uses
                INNER JOIN (
                    SELECT code_id, MAX(used_at) AS last_used_at
                    FROM {$uses}
                    GROUP BY code_id
                ) AS max_uses ON uses.code_id = max_uses.code_id AND uses.used_at = max_uses.last_used_at
            ) AS last_uses ON codes.id = last_uses.code_id
            LEFT JOIN {$users} AS users ON last_uses.last_user_id = users.ID";

        if ( $project_id ) {
            return $wpdb->get_results(
                $wpdb->prepare( $select . $from . ' WHERE codes.project_id = %d ORDER BY codes.created_at DESC', $project_id )
            );
        }

        return $wpdb->get_results( $select . $from . ' ORDER BY codes.created_at DESC' );
    }

    public static function get_project_code_counts( $project_id ) {
        global $wpdb;
        $codes = self::table( 'codes' );
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    SUM(CASE WHEN uses_count >= max_uses THEN 1 ELSE 0 END) AS used_count,
                    SUM(CASE WHEN uses_count < max_uses AND is_active = 1 THEN 1 ELSE 0 END) AS available_count
                FROM {$codes}
                WHERE project_id = %d",
                $project_id
            )
        );
    }

    public static function generate_codes( $project_id, $quantity, $length, $prefix, $max_uses ) {
        global $wpdb;
        $table = self::table( 'codes' );
        $now   = current_time( 'mysql' );
        $created = 0;

        while ( $created < $quantity ) {
            $code = co360_generate_code( $length );
            if ( $prefix ) {
                $code = $prefix . $code;
            }
            $code = co360_normalize_code( $code );

            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $code ) );
            if ( $exists ) {
                continue;
            }

            $inserted = $wpdb->insert(
                $table,
                array(
                    'project_id' => $project_id,
                    'code'       => $code,
                    'max_uses'   => $max_uses,
                    'uses_count' => 0,
                    'is_active'  => 1,
                    'created_at' => $now,
                ),
                array( '%d', '%s', '%d', '%d', '%d', '%s' )
            );

            if ( $inserted ) {
                $created++;
            }
        }

        return $created;
    }

    public static function get_code_for_project( $project_id, $code ) {
        global $wpdb;
        $table = self::table( 'codes' );
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE project_id = %d AND code = %s",
                $project_id,
                $code
            )
        );
    }

    public static function increment_code_use( $code_id ) {
        global $wpdb;
        $table = self::table( 'codes' );
        $sql   = $wpdb->prepare(
            "UPDATE {$table} SET uses_count = uses_count + 1 WHERE id = %d AND uses_count < max_uses",
            $code_id
        );
        $wpdb->query( $sql );
        return $wpdb->rows_affected;
    }

    public static function insert_use_log( $data ) {
        global $wpdb;
        $table = self::table( 'uses' );
        $wpdb->insert(
            $table,
            $data,
            array( '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
        );
    }

    public static function get_uses( $project_id = 0 ) {
        global $wpdb;
        $table = self::table( 'uses' );
        if ( $project_id ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE project_id = %d ORDER BY used_at DESC", $project_id ) );
        }
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY used_at DESC" );
    }

    public static function get_mappings() {
        global $wpdb;
        $table = self::table( 'gf_mappings' );
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
    }

    public static function get_mapping_group( $course_id, $form_id, $field_id ) {
        global $wpdb;
        $table = self::table( 'gf_mappings' );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE course_id = %d AND form_id = %d AND field_id = %s",
                $course_id,
                $form_id,
                $field_id
            )
        );
    }

    public static function get_course_ids() {
        global $wpdb;
        $table = self::table( 'gf_mappings' );
        return $wpdb->get_col( "SELECT DISTINCT course_id FROM {$table} ORDER BY course_id ASC" );
    }

    public static function get_mapping_groups( $course_id = null ) {
        global $wpdb;
        $table    = self::table( 'gf_mappings' );
        $projects = self::table( 'projects' );

        $sql = "SELECT
            maps.course_id,
            maps.form_id,
            maps.field_id,
            GROUP_CONCAT(projects.name ORDER BY projects.name SEPARATOR ', ') AS project_names
        FROM {$table} AS maps
        LEFT JOIN {$projects} AS projects ON maps.project_id = projects.id";

        if ( null !== $course_id ) {
            $sql .= $wpdb->prepare( ' WHERE maps.course_id = %d', $course_id );
        }

        $sql .= ' GROUP BY maps.course_id, maps.form_id, maps.field_id ORDER BY maps.course_id ASC, maps.form_id ASC';

        return $wpdb->get_results( $sql );
    }

    public static function get_distinct_course_ids_for_form_field( $form_id, $field_id ) {
        global $wpdb;
        $table = self::table( 'gf_mappings' );
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT course_id FROM {$table} WHERE form_id = %d AND field_id = %s",
                $form_id,
                $field_id
            )
        );
    }

    public static function mapping_exists( $form_id, $field_id, $course_id, $project_id ) {
        global $wpdb;
        $table = self::table( 'gf_mappings' );
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE form_id = %d AND field_id = %s AND course_id = %d AND project_id = %d",
                $form_id,
                $field_id,
                $course_id,
                $project_id
            )
        );
    }

    public static function add_mapping( $form_id, $field_id, $course_id, $project_id ) {
        global $wpdb;
        $table = self::table( 'gf_mappings' );
        $wpdb->insert(
            $table,
            array(
                'form_id'    => $form_id,
                'field_id'   => $field_id,
                'course_id'  => $course_id,
                'project_id' => $project_id,
            ),
            array( '%d', '%s', '%d', '%d' )
        );
    }

    public static function delete_mapping_group( $course_id, $form_id, $field_id ) {
        global $wpdb;
        $table = self::table( 'gf_mappings' );
        $wpdb->delete(
            $table,
            array(
                'course_id' => $course_id,
                'form_id'   => $form_id,
                'field_id'  => $field_id,
            ),
            array( '%d', '%d', '%s' )
        );
    }

    public static function get_mappings_for_form( $form_id ) {
        global $wpdb;
        $table = self::table( 'gf_mappings' );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE form_id = %d",
                $form_id
            )
        );
    }

    public static function get_mappings_for_form_field( $form_id, $field_id ) {
        global $wpdb;
        $table = self::table( 'gf_mappings' );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE form_id = %d AND field_id = %s",
                $form_id,
                $field_id
            )
        );
    }

    public static function find_code_in_projects( $code, $project_ids ) {
        global $wpdb;
        $table = self::table( 'codes' );
        $project_ids = array_map( 'absint', (array) $project_ids );
        $project_ids = array_filter( $project_ids );

        if ( empty( $project_ids ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $project_ids ), '%d' ) );
        $query        = "SELECT * FROM {$table}
            WHERE code = %s
            AND is_active = 1
            AND uses_count < max_uses
            AND project_id IN ({$placeholders})";

        $params = array_merge( array( $code ), $project_ids );
        return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
    }
}
