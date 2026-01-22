<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CO360_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_co360_save_project', array( $this, 'handle_save_project' ) );
        add_action( 'admin_post_co360_toggle_project', array( $this, 'handle_toggle_project' ) );
        add_action( 'admin_post_co360_generate_codes', array( $this, 'handle_generate_codes' ) );
        add_action( 'admin_post_co360_export_codes', array( $this, 'handle_export_codes' ) );
        add_action( 'admin_post_co360_add_mapping', array( $this, 'handle_add_mapping' ) );
        add_action( 'admin_post_co360_delete_mapping', array( $this, 'handle_delete_mapping' ) );
    }

    public function register_menu() {
        add_menu_page(
            __( 'CO360 Asistencia', 'co360-attendance-codes' ),
            __( 'CO360 Asistencia', 'co360-attendance-codes' ),
            'manage_options',
            'co360-attendance',
            array( $this, 'render_projects_page' ),
            'dashicons-clipboard'
        );

        add_submenu_page(
            'co360-attendance',
            __( 'Proyectos', 'co360-attendance-codes' ),
            __( 'Proyectos', 'co360-attendance-codes' ),
            'manage_options',
            'co360-attendance',
            array( $this, 'render_projects_page' )
        );

        add_submenu_page(
            'co360-attendance',
            __( 'Códigos', 'co360-attendance-codes' ),
            __( 'Códigos', 'co360-attendance-codes' ),
            'manage_options',
            'co360-attendance-codes',
            array( $this, 'render_codes_page' )
        );

        add_submenu_page(
            'co360-attendance',
            __( 'Usos', 'co360-attendance-codes' ),
            __( 'Usos', 'co360-attendance-codes' ),
            'manage_options',
            'co360-attendance-uses',
            array( $this, 'render_uses_page' )
        );

        add_submenu_page(
            'co360-attendance',
            __( 'Ajustes', 'co360-attendance-codes' ),
            __( 'Ajustes', 'co360-attendance-codes' ),
            'manage_options',
            'co360-attendance-settings',
            array( $this, 'render_settings_page' )
        );
    }

    private function check_permissions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'co360-attendance-codes' ) );
        }
    }

    public function handle_save_project() {
        $this->check_permissions();
        check_admin_referer( 'co360_save_project' );

        $project_id  = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
        $status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';

        if ( empty( $name ) ) {
            wp_redirect( admin_url( 'admin.php?page=co360-attendance&message=missing_name' ) );
            exit;
        }

        if ( $project_id ) {
            CO360_DB::update_project( $project_id, $name, $description, $status );
        } else {
            CO360_DB::insert_project( $name, $description );
        }

        wp_redirect( admin_url( 'admin.php?page=co360-attendance&message=saved' ) );
        exit;
    }

    public function handle_toggle_project() {
        $this->check_permissions();
        check_admin_referer( 'co360_toggle_project' );

        $project_id = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
        if ( $project_id ) {
            CO360_DB::toggle_project_status( $project_id );
        }

        wp_redirect( admin_url( 'admin.php?page=co360-attendance' ) );
        exit;
    }

    public function handle_generate_codes() {
        $this->check_permissions();
        check_admin_referer( 'co360_generate_codes' );

        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $quantity   = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;
        $length     = isset( $_POST['length'] ) ? absint( $_POST['length'] ) : 8;
        $prefix     = isset( $_POST['prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix'] ) ) : '';
        $max_uses   = isset( $_POST['max_uses'] ) ? absint( $_POST['max_uses'] ) : 1;

        if ( $project_id && $quantity > 0 ) {
            CO360_DB::generate_codes( $project_id, $quantity, $length, $prefix, $max_uses );
        }

        wp_redirect( admin_url( 'admin.php?page=co360-attendance-codes&project_id=' . $project_id ) );
        exit;
    }

    public function handle_export_codes() {
        $this->check_permissions();
        check_admin_referer( 'co360_export_codes' );

        $project_id = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
        if ( $project_id ) {
            CO360_CSV::export_codes( $project_id );
        }

        wp_redirect( admin_url( 'admin.php?page=co360-attendance-codes' ) );
        exit;
    }

    public function handle_add_mapping() {
        $this->check_permissions();
        check_admin_referer( 'co360_add_mapping' );

        $form_id    = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $field_id   = isset( $_POST['field_id'] ) ? sanitize_text_field( wp_unslash( $_POST['field_id'] ) ) : '';
        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;

        if ( $form_id && $field_id && $project_id ) {
            CO360_DB::add_mapping( $form_id, $field_id, $project_id );
        }

        wp_redirect( admin_url( 'admin.php?page=co360-attendance-settings' ) );
        exit;
    }

    public function handle_delete_mapping() {
        $this->check_permissions();
        check_admin_referer( 'co360_delete_mapping' );

        $mapping_id = isset( $_GET['mapping_id'] ) ? absint( $_GET['mapping_id'] ) : 0;
        if ( $mapping_id ) {
            CO360_DB::delete_mapping( $mapping_id );
        }

        wp_redirect( admin_url( 'admin.php?page=co360-attendance-settings' ) );
        exit;
    }

    public function render_projects_page() {
        $this->check_permissions();
        $projects = CO360_DB::get_projects();
        $editing  = null;

        if ( isset( $_GET['action'], $_GET['project_id'] ) && 'edit' === $_GET['action'] ) {
            $editing = CO360_DB::get_project( absint( $_GET['project_id'] ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Proyectos', 'co360-attendance-codes' ); ?></h1>

            <h2><?php echo $editing ? esc_html__( 'Editar proyecto', 'co360-attendance-codes' ) : esc_html__( 'Añadir proyecto', 'co360-attendance-codes' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'co360_save_project' ); ?>
                <input type="hidden" name="action" value="co360_save_project" />
                <?php if ( $editing ) : ?>
                    <input type="hidden" name="project_id" value="<?php echo esc_attr( $editing->id ); ?>" />
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="co360-project-name"><?php esc_html_e( 'Nombre', 'co360-attendance-codes' ); ?></label></th>
                        <td><input name="name" id="co360-project-name" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="co360-project-description"><?php esc_html_e( 'Descripción', 'co360-attendance-codes' ); ?></label></th>
                        <td><textarea name="description" id="co360-project-description" rows="3" class="large-text"><?php echo esc_textarea( $editing ? $editing->description : '' ); ?></textarea></td>
                    </tr>
                    <?php if ( $editing ) : ?>
                        <tr>
                            <th scope="row"><label for="co360-project-status"><?php esc_html_e( 'Estado', 'co360-attendance-codes' ); ?></label></th>
                            <td>
                                <select name="status" id="co360-project-status">
                                    <option value="active" <?php selected( $editing->status, 'active' ); ?>><?php esc_html_e( 'Activo', 'co360-attendance-codes' ); ?></option>
                                    <option value="archived" <?php selected( $editing->status, 'archived' ); ?>><?php esc_html_e( 'Archivado', 'co360-attendance-codes' ); ?></option>
                                </select>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
                <?php submit_button( $editing ? __( 'Guardar cambios', 'co360-attendance-codes' ) : __( 'Crear proyecto', 'co360-attendance-codes' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Listado de proyectos', 'co360-attendance-codes' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Nombre', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Total códigos', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Usados', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Disponibles', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Acciones', 'co360-attendance-codes' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $projects ) ) : ?>
                    <tr><td colspan="6"><?php esc_html_e( 'No hay proyectos todavía.', 'co360-attendance-codes' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $projects as $project ) :
                        $stats  = CO360_DB::get_project_stats( $project->id );
                        $toggle = wp_nonce_url(
                            admin_url( 'admin-post.php?action=co360_toggle_project&project_id=' . $project->id ),
                            'co360_toggle_project'
                        );
                        $edit_url = admin_url( 'admin.php?page=co360-attendance&action=edit&project_id=' . $project->id );
                        $codes_url = admin_url( 'admin.php?page=co360-attendance-codes&project_id=' . $project->id );
                        $export_url = wp_nonce_url(
                            admin_url( 'admin-post.php?action=co360_export_codes&project_id=' . $project->id ),
                            'co360_export_codes'
                        );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $project->name ); ?></td>
                            <td><?php echo esc_html( $project->status ); ?></td>
                            <td><?php echo esc_html( $stats ? $stats->total : 0 ); ?></td>
                            <td><?php echo esc_html( $stats ? $stats->used : 0 ); ?></td>
                            <td><?php echo esc_html( $stats ? $stats->available : 0 ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Editar', 'co360-attendance-codes' ); ?></a> |
                                <a href="<?php echo esc_url( $toggle ); ?>"><?php echo esc_html( 'active' === $project->status ? __( 'Archivar', 'co360-attendance-codes' ) : __( 'Activar', 'co360-attendance-codes' ) ); ?></a> |
                                <a href="<?php echo esc_url( $codes_url ); ?>"><?php esc_html_e( 'Ver códigos', 'co360-attendance-codes' ); ?></a> |
                                <a href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Exportar CSV', 'co360-attendance-codes' ); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_codes_page() {
        $this->check_permissions();
        $project_id = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
        $projects   = CO360_DB::get_projects();
        $codes      = $project_id ? CO360_DB::get_codes( $project_id ) : array();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Códigos', 'co360-attendance-codes' ); ?></h1>

            <form method="get" action="">
                <input type="hidden" name="page" value="co360-attendance-codes" />
                <select name="project_id">
                    <option value="0"><?php esc_html_e( 'Seleccionar proyecto', 'co360-attendance-codes' ); ?></option>
                    <?php foreach ( $projects as $project ) : ?>
                        <option value="<?php echo esc_attr( $project->id ); ?>" <?php selected( $project_id, $project->id ); ?>>
                            <?php echo esc_html( $project->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'Filtrar', 'co360-attendance-codes' ), 'secondary', '', false ); ?>
            </form>

            <?php if ( $project_id ) : ?>
                <h2><?php esc_html_e( 'Generar lote', 'co360-attendance-codes' ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'co360_generate_codes' ); ?>
                    <input type="hidden" name="action" value="co360_generate_codes" />
                    <input type="hidden" name="project_id" value="<?php echo esc_attr( $project_id ); ?>" />
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="co360-quantity"><?php esc_html_e( 'Cantidad', 'co360-attendance-codes' ); ?></label></th>
                            <td><input name="quantity" id="co360-quantity" type="number" min="1" value="10"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="co360-length"><?php esc_html_e( 'Longitud', 'co360-attendance-codes' ); ?></label></th>
                            <td><input name="length" id="co360-length" type="number" min="4" value="8"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="co360-prefix"><?php esc_html_e( 'Prefijo', 'co360-attendance-codes' ); ?></label></th>
                            <td><input name="prefix" id="co360-prefix" type="text" class="regular-text" placeholder="TALLER-"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="co360-max-uses"><?php esc_html_e( 'Usos máximos', 'co360-attendance-codes' ); ?></label></th>
                            <td><input name="max_uses" id="co360-max-uses" type="number" min="1" value="1"></td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Generar', 'co360-attendance-codes' ) ); ?>
                </form>

                <h2><?php esc_html_e( 'Listado de códigos', 'co360-attendance-codes' ); ?></h2>
                <p>
                    <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=co360_export_codes&project_id=' . $project_id ), 'co360_export_codes' ) ); ?>">
                        <?php esc_html_e( 'Exportar CSV', 'co360-attendance-codes' ); ?>
                    </a>
                </p>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Código', 'co360-attendance-codes' ); ?></th>
                            <th><?php esc_html_e( 'Usos', 'co360-attendance-codes' ); ?></th>
                            <th><?php esc_html_e( 'Estado', 'co360-attendance-codes' ); ?></th>
                            <th><?php esc_html_e( 'Creado', 'co360-attendance-codes' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $codes ) ) : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'No hay códigos para este proyecto.', 'co360-attendance-codes' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $codes as $code ) : ?>
                            <tr>
                                <td><?php echo esc_html( $code->code ); ?></td>
                                <td><?php echo esc_html( $code->uses_count . '/' . $code->max_uses ); ?></td>
                                <td><?php echo esc_html( $code->uses_count >= $code->max_uses ? __( 'Usado', 'co360-attendance-codes' ) : __( 'No usado', 'co360-attendance-codes' ) ); ?></td>
                                <td><?php echo esc_html( $code->created_at ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_uses_page() {
        $this->check_permissions();
        $project_id = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
        $projects   = CO360_DB::get_projects();
        $uses       = CO360_DB::get_uses( $project_id );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Usos', 'co360-attendance-codes' ); ?></h1>

            <form method="get" action="">
                <input type="hidden" name="page" value="co360-attendance-uses" />
                <select name="project_id">
                    <option value="0"><?php esc_html_e( 'Todos los proyectos', 'co360-attendance-codes' ); ?></option>
                    <?php foreach ( $projects as $project ) : ?>
                        <option value="<?php echo esc_attr( $project->id ); ?>" <?php selected( $project_id, $project->id ); ?>>
                            <?php echo esc_html( $project->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'Filtrar', 'co360-attendance-codes' ), 'secondary', '', false ); ?>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Fecha', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Proyecto', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Código', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Usuario', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'GF Entry', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'IP', 'co360-attendance-codes' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $uses ) ) : ?>
                    <tr><td colspan="6"><?php esc_html_e( 'Sin registros todavía.', 'co360-attendance-codes' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $uses as $use ) : ?>
                        <?php $project = CO360_DB::get_project( $use->project_id ); ?>
                        <tr>
                            <td><?php echo esc_html( $use->used_at ); ?></td>
                            <td><?php echo esc_html( $project ? $project->name : '' ); ?></td>
                            <td><?php echo esc_html( $use->code ); ?></td>
                            <td><?php echo esc_html( $use->user_id ); ?></td>
                            <td><?php echo esc_html( $use->gf_entry_id ); ?></td>
                            <td><?php echo esc_html( $use->ip ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_settings_page() {
        $this->check_permissions();
        $projects = CO360_DB::get_projects();
        $maps     = CO360_DB::get_mappings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Ajustes', 'co360-attendance-codes' ); ?></h1>

            <h2><?php esc_html_e( 'Mapeos Gravity Forms', 'co360-attendance-codes' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'co360_add_mapping' ); ?>
                <input type="hidden" name="action" value="co360_add_mapping" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="co360-form-id"><?php esc_html_e( 'Form ID', 'co360-attendance-codes' ); ?></label></th>
                        <td><input name="form_id" id="co360-form-id" type="number" min="1" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="co360-field-id"><?php esc_html_e( 'Field ID', 'co360-attendance-codes' ); ?></label></th>
                        <td><input name="field_id" id="co360-field-id" type="text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="co360-map-project"><?php esc_html_e( 'Proyecto', 'co360-attendance-codes' ); ?></label></th>
                        <td>
                            <select name="project_id" id="co360-map-project" required>
                                <option value=""><?php esc_html_e( 'Seleccionar', 'co360-attendance-codes' ); ?></option>
                                <?php foreach ( $projects as $project ) : ?>
                                    <option value="<?php echo esc_attr( $project->id ); ?>"><?php echo esc_html( $project->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Añadir mapeo', 'co360-attendance-codes' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Mapeos existentes', 'co360-attendance-codes' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Form ID', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Field ID', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Proyecto', 'co360-attendance-codes' ); ?></th>
                        <th><?php esc_html_e( 'Acciones', 'co360-attendance-codes' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $maps ) ) : ?>
                    <tr><td colspan="4"><?php esc_html_e( 'Sin mapeos todavía.', 'co360-attendance-codes' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $maps as $map ) : ?>
                        <?php $project = CO360_DB::get_project( $map->project_id ); ?>
                        <tr>
                            <td><?php echo esc_html( $map->form_id ); ?></td>
                            <td><?php echo esc_html( $map->field_id ); ?></td>
                            <td><?php echo esc_html( $project ? $project->name : '' ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=co360_delete_mapping&mapping_id=' . $map->id ), 'co360_delete_mapping' ) ); ?>">
                                    <?php esc_html_e( 'Eliminar', 'co360-attendance-codes' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
