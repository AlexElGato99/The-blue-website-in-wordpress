<?php
if (!defined('WPVIVID_PLUGIN_DIR'))
{
    die;
}

if ( ! class_exists( 'WP_List_Table' ) )
{
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPvivid_Staging_Free
{
    public $main_tab;
    public $ajax;
    public $end_shutdown_function;
    public $screen_ids;

    public $log;
    public $log_page;
    public $new_wp_page;
    public $ui_display;
    public $setting;

    public function __construct()
    {
        if(is_admin())
        {
            include_once WPVIVID_PLUGIN_DIR . '/includes/staging/class-wpvivid-staging-copy-db-ex.php';
            include_once WPVIVID_PLUGIN_DIR . '/includes/staging/class-wpvivid-staging-copy-files-ex.php';
            include_once WPVIVID_PLUGIN_DIR . '/includes/staging/class-wpvivid-staging-task-ex.php';
            include_once WPVIVID_PLUGIN_DIR . '/includes/staging/class-wpvivid-staging-log.php';
            include_once WPVIVID_PLUGIN_DIR . '/includes/staging/class-wpvivid-staging-log-page.php';
            include_once WPVIVID_PLUGIN_DIR . '/includes/staging/class-wpvivid-staging-create-new-wp.php';
            include_once WPVIVID_PLUGIN_DIR . '/includes/staging/class-wpvivid-staging-ui-display.php';
            include_once WPVIVID_PLUGIN_DIR . '/includes/staging/class-wpvivid-staging-setting.php';
            include_once WPVIVID_PLUGIN_DIR . '/includes/staging/class-wpvivid-staging-sites-list.php';

            $this->log=new WPvivid_Staging_Log_Free();
            $this->log_page=new WPvivid_Staging_Log_Page_Free();
            $this->ui_display=new WPvivid_Staging_UI_Display_Free();
            $this->setting=new WPvivid_Staging_Setting_Free();


            add_action('admin_enqueue_scripts',array( $this,'enqueue_styles'));
            add_action('admin_enqueue_scripts',array( $this,'enqueue_scripts'));

            //add_filter('wpvivid_add_side_bar', array($this, 'add_side_bar'), 11, 2);
            add_action('wpvivid_add_staging_side_bar_ex', array($this, 'add_side_bar'), 11, 2);

            add_filter('wpvivid_get_toolbar_menus',array($this,'get_toolbar_menus'),22);
            add_filter('wpvivid_get_admin_menus',array($this,'get_admin_menus'),22);
            add_filter('wpvivid_get_screen_ids',array($this,'get_screen_ids'),12);

            require_once WPVIVID_PLUGIN_DIR . '/includes/staging/class-wpvivid-staging-ajax.php';
            $this->ajax = new WPvivid_Staging_Ajax_Free($this);
        }

        add_filter('wpvividstg_get_admin_url',array($this,'get_admin_url'),10);
        add_filter('wpvivid_add_staging_side_bar', array($this, 'wpvivid_add_staging_side_bar'), 11, 2);

        add_action( "init",array($this,'staging_site'));
    }

    public function add_side_bar($html, $show_schedule = false)
    {
        if(get_current_screen()->id=='wpvivid-backup_page_wpvivid-staging')
        {
            $wpvivid_version = WPVIVID_PLUGIN_VERSION;
            $wpvivid_version = apply_filters('wpvivid_display_pro_version', $wpvivid_version);

            ?>
            <div class="postbox">
                <h2>
                    <div style="float: left; margin-right: 5px;"><span style="margin: 0; padding: 0"><?php esc_html_e('Current Version: ', 'wpvivid-backuprestore'); ?><?php echo esc_html($wpvivid_version); ?></span></div>
                    <div style="float: left; margin-right: 5px;"><span style="margin: 0; padding: 0">|</span></div>
                    <div style="float: left; margin-left: 0;">
                        <span style="margin: 0; padding: 0"><a href="https://wordpress.org/plugins/wpvivid-backuprestore/#developers" target="_blank" style="text-decoration: none;"><?php esc_html_e('ChangeLog', 'wpvivid-backuprestore'); ?></a></span>
                    </div>
                    <div style="clear: both;"></div>
                </h2>
            </div>
            <div id="wpvivid_backup_schedule_part"></div>
            <div class="postbox">
                <h2><span><?php esc_html_e('Troubleshooting', 'wpvivid-backuprestore'); ?></span></h2>
                <div class="inside">
                    <table class="widefat" cellpadding="0">
                        <tbody>
                        <tr class="alternate">
                            <td class="row-title">'Read <a href="https://docs.wpvivid.com/troubleshooting-issues-wpvivid-backup-plugin.html" target="_blank">Troubleshooting page</a> for faster solutions.'</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="postbox">
                <h2><span><?php esc_html_e('How-to', 'wpvivid-backuprestore'); ?></span></h2>
                <div class="inside">
                    <table class="widefat" cellpadding="0">
                        <tbody>
                        <tr class="alternate"><td class="row-title"><a href="https://docs.wpvivid.com/wpvivid-backup-pro-create-staging-site.html" target="_blank"><?php esc_html_e('Create A Staging Site', 'wpvivid-backuprestore'); ?></a></td></tr>
                        <tr><td class="row-title"><a href="https://docs.wpvivid.com/wpvivid-staging-pro-create-fresh-install.html" target="_blank"><?php esc_html_e('Create A Fresh WordPress Install', 'wpvivid-backuprestore'); ?></a></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
            /*$html = '<div class="postbox">
                <h2>
                    <div style="float: left; margin-right: 5px;"><span style="margin: 0; padding: 0">'.__('Current Version:', 'wpvivid-backuprestore').' '.$wpvivid_version.'</span></div>
                    <div style="float: left; margin-right: 5px;"><span style="margin: 0; padding: 0">|</span></div>
                    <div style="float: left; margin-left: 0;">
                        <span style="margin: 0; padding: 0"><a href="https://wordpress.org/plugins/wpvivid-backuprestore/#developers" target="_blank" style="text-decoration: none;">'.__('ChangeLog', 'wpvivid-backuprestore').'</a></span>
                    </div>
                    <div style="clear: both;"></div>
                </h2>
             </div>
             <div id="wpvivid_backup_schedule_part"></div>
             <div class="postbox">
                <h2><span>'.__('Troubleshooting', 'wpvivid-backuprestore').'</span></h2>
                <div class="inside">
                    <table class="widefat" cellpadding="0">
                        <tbody>
                        <tr class="alternate">
                            <td class="row-title">'.__('Read <a href="https://docs.wpvivid.com/troubleshooting-issues-wpvivid-backup-plugin.html" target="_blank">Troubleshooting page</a> for faster solutions.', 'wpvivid-backuprestore').'</td>
                        </tr>             
                        </tbody>
                    </table>
                </div>
             </div>
             <div class="postbox">
                <h2><span>'.__('How-to', 'wpvivid-backuprestore').'</span></h2>
                <div class="inside">
                    <table class="widefat" cellpadding="0">
                        <tbody>
                            <tr class="alternate"><td class="row-title"><a href="https://docs.wpvivid.com/wpvivid-backup-pro-create-staging-site.html" target="_blank">'.__('Create A Staging Site', 'wpvivid-backuprestore').'</a></td></tr>
                            <tr><td class="row-title"><a href="https://docs.wpvivid.com/wpvivid-staging-pro-create-fresh-install.html" target="_blank">'.__('Create A Fresh WordPress Install', 'wpvivid-backuprestore').'</a></td></tr>
                        </tbody>
                    </table>
                </div>
             </div>';*/
        }

        //return $html;
    }

    public function get_admin_url($admin_url)
    {
        if(is_multisite())
        {
            $admin_url = network_admin_url();
        }
        else
        {
            $admin_url =admin_url();
        }

        return $admin_url;
    }

    public function wpvivid_add_staging_side_bar($html, $show_schedule)
    {
        $html = '<h2 style="margin-top:0.5em;">
                     <span class="dashicons dashicons-sticky wpvivid-dashicons-orange"></span>
                     <span>Troubleshooting</span>
                 </h2>
                 <div class="inside" style="padding-top:0;">
                     <ul class="" >
                        <li style="border-top:1px solid #f1f1f1;"><span class="dashicons dashicons-editor-help wpvivid-dashicons-orange" ></span>
                            <a href="https://docs.wpvivid.com/troubleshooting-issues-wpvivid-staging-pro.html"><b>Troubleshooting</b></a>
                            <small><span style="float: right;"><a href="https://wpvivid.com/troubleshooting-issues-wpvivid-staging-pro" style="text-decoration: none;" target="_blank"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                        </li>
                     </ul>
                 </div>
                 
                 <h2>
                     <span class="dashicons dashicons-book-alt wpvivid-dashicons-orange" ></span>
                     <span>Documentation</span>
                 </h2>
                 <div class="inside" style="padding-top:0;">
                     <ul class="">
                        <li style="border-top:1px solid #f1f1f1;"><span class="dashicons dashicons-migrate wpvivid-dashicons-blue"></span>
                            <a href="https://docs.wpvivid.com/wpvivid-backup-pro-create-staging-site.html"><b>Create A Staging Site</b></a>
                            <small><span style="float: right;"><a href="https://wpvivid.com/wpvivid-backup-pro-create-staging-site" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                        </li>
                        <li><span class="dashicons dashicons-migrate wpvivid-dashicons-blue"></span>
                            <a href="https://docs.wpvivid.com/wpvivid-staging-pro-publish-staging-to-live.html"><b>Publish A Staging Site</b></a>
                            <small><span style="float: right;"><a href="https://wpvivid.com/wpvivid-backup-pro-publish-staging-to-live" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                        </li>
                        <li><span class="dashicons dashicons-migrate wpvivid-dashicons-blue"></span>
                            <a href="https://docs.wpvivid.com/wpvivid-staging-pro-create-staging-site-for-wordpress-multisite.html"><b>Create A MU Staging</b></a>
                            <small><span style="float: right;"><a href="https://wpvivid.com/wpvivid-staging-pro-create-staging-site-for-wordpress-multisite" style="text-decoration: none;"><span class="dashicons dashicons-migrate wpvivid-dashicons-grey"></span></a></span></small><br>
                        </li>
                     </ul>
                 </div>';
        return $html;
    }

    public function enqueue_styles()
    {
        $this->screen_ids=apply_filters('wpvivid_get_screen_ids',$this->screen_ids);
        if(get_current_screen()->id=='wpvivid-backup_page_wpvivid-staging')
        {
            wp_enqueue_style(WPVIVID_PLUGIN_SLUG.'jstree', WPVIVID_PLUGIN_DIR_URL . 'js/jstree/dist/themes/default/style.min.css', array(), WPVIVID_PLUGIN_VERSION, 'all');
            wp_enqueue_style(WPVIVID_PLUGIN_SLUG.'staging', WPVIVID_PLUGIN_DIR_URL . 'css/wpvivid-staging-custom.css', array(), WPVIVID_PLUGIN_VERSION, 'all');
        }
    }

    public function enqueue_scripts()
    {
        $this->screen_ids=apply_filters('wpvivid_get_screen_ids',$this->screen_ids);
        if(in_array(get_current_screen()->id,$this->screen_ids))
        {
            wp_enqueue_script(WPVIVID_PLUGIN_SLUG.'jstree', WPVIVID_PLUGIN_DIR_URL . 'js/jstree/dist/jstree.min.js', array('jquery'), WPVIVID_PLUGIN_VERSION, false);
            wp_enqueue_script('plupload-all');
        }
    }

    public function get_screen_ids($screen_ids)
    {
        $screen_ids[]=apply_filters('wpvivid_white_label_screen_id', 'wpvivid-backup_page_wpvivid-staging');
        return $screen_ids;
    }

    public function get_toolbar_menus($toolbar_menus)
    {
        $admin_url = apply_filters('wpvivid_get_admin_url', '');

        $menu['id']='wpvivid_admin_menu_staging';
        $menu['parent']='wpvivid_admin_menu';
        $menu['title']=__('Staging', 'wpvivid-backuprestore');
        $menu['tab']= 'admin.php?page='.apply_filters('wpvivid_white_label_plugin_name', 'wpvivid-staging');
        $menu['href']=$admin_url . 'admin.php?page='.apply_filters('wpvivid_white_label_plugin_name', 'wpvivid-staging');
        $menu['capability']='administrator';
        $menu['index']=3;
        $toolbar_menus[$menu['parent']]['child'][$menu['id']]=$menu;
        return $toolbar_menus;
    }

    public function get_admin_menus($submenus)
    {
        $submenu['parent_slug']=apply_filters('wpvivid_white_label_slug', WPVIVID_PLUGIN_SLUG);
        $submenu['page_title']= apply_filters('wpvivid_white_label_display', 'WPvivid Backup');
        $submenu['menu_title']=__('Staging', 'wpvivid-backuprestore');
        $submenu['capability']='administrator';
        $submenu['menu_slug']=strtolower(sprintf('%s-staging', apply_filters('wpvivid_white_label_slug', 'wpvivid')));
        $submenu['index']=3;
        $submenu['function']=array($this, 'display_plugin_setup_page');
        $submenus[$submenu['menu_slug']]=$submenu;
        return $submenus;
    }

    public function display_plugin_setup_page()
    {
        ?>
        <?php
        $this->ui_display->init_page();
        ?>
        <?php
    }

    public function get_database_site_url()
    {
        $site_url = site_url();
        global $wpdb;
        $site_url_sql = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->options WHERE option_name = %s", 'siteurl' ) );
        foreach ( $site_url_sql as $site ){
            $site_url = $site->option_value;
        }
        return untrailingslashit($site_url);
    }

    public function get_database_home_url()
    {
        $home_url = home_url();
        global $wpdb;
        $home_url_sql = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->options WHERE option_name = %s", 'home' ) );
        foreach ( $home_url_sql as $home ){
            $home_url = $home->option_value;
        }
        return untrailingslashit($home_url);
    }

    public static function get_custom_path_size($type, $path, $size=0){
        if(!function_exists('get_home_path'))
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        $home_path = str_replace('\\','/', get_home_path());
        $core_file_arr = array('.htaccess', 'index', 'license.txt', 'readme.html', 'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php', 'wp-config.php', 'wp-config-sample.php',
            'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php', 'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php');
        if(is_dir($path))
        {
            $handler = opendir($path);
            if($handler!==false)
            {
                while (($filename = readdir($handler)) !== false)
                {
                    if ($filename != "." && $filename != "..") {
                        if (is_dir($path . DIRECTORY_SEPARATOR . $filename))
                        {
                            if($type === 'content'){
                                if($filename !== 'plugins' && $filename !== 'themes' && $filename !== 'uploads'){
                                    $size=self::get_custom_path_size($type, $path . DIRECTORY_SEPARATOR . $filename, $size);
                                }
                            }
                            else if($type === 'core' && $home_path === $path){
                                if($filename === 'wp-admin' || $filename === 'wp-includes'){
                                    $size=self::get_custom_path_size($type, $path . DIRECTORY_SEPARATOR . $filename, $size);
                                }
                            }
                            else if($type === 'additional'){
                                if($filename !== 'wp-admin' && $filename !== 'wp-content' && $filename !== 'wp-includes'){
                                    $size=self::get_custom_path_size($type, $path . DIRECTORY_SEPARATOR . $filename, $size);
                                }
                            }
                            else{
                                $size=self::get_custom_path_size($type, $path . DIRECTORY_SEPARATOR . $filename, $size);
                            }
                        } else {
                            if($type === 'core'){
                                if($home_path === $path){
                                    if(in_array($filename, $core_file_arr)){
                                        $size+=filesize($path . DIRECTORY_SEPARATOR . $filename);
                                    }
                                }
                                else{
                                    $size+=filesize($path . DIRECTORY_SEPARATOR . $filename);
                                }
                            }
                            else if($type === 'additional'){
                                if($home_path === $path){
                                    if(!in_array($filename, $core_file_arr)){
                                        $size+=filesize($path . DIRECTORY_SEPARATOR . $filename);
                                    }
                                }
                                else{
                                    $size+=filesize($path . DIRECTORY_SEPARATOR . $filename);
                                }
                            }
                            else{
                                $size+=filesize($path . DIRECTORY_SEPARATOR . $filename);
                            }
                        }
                    }
                }
                if($handler)
                    @closedir($handler);
            }

        }
        return $size;
    }

    public function get_staging_site_data()
    {
        if(is_multisite())
        {
            switch_to_blog(get_main_site_id());
            $staging=get_option('wpvivid_staging_data',false);
            restore_current_blog();
        }
        else
        {
            $staging=get_option('wpvivid_staging_data',false);
        }

        return $staging;
    }

    public function wpvivid_replace_directory( $path ) {
        return preg_replace( '/[\\\\]+/', '/', $path );
    }

    public function getPath( $path, $wpcontentDir, $directory ) {
        $realPath = $this->wpvivid_replace_directory($directory->getRealPath());
        if( false === strpos( $realPath, $path ) ) {
            return false;
        }

        $path = str_replace( $wpcontentDir . '/', null, $this->wpvivid_replace_directory($directory->getRealPath()) );
        // Using strpos() for symbolic links as they could create nasty stuff in nix stuff for directory structures
        if( !$directory->isDir() ||
            strlen( $path ) < 1 ||
            (strpos( $this->wpvivid_replace_directory($directory->getRealPath()), $wpcontentDir . '/' . 'plugins' ) !== 0 &&
                strpos( $this->wpvivid_replace_directory($directory->getRealPath()), $wpcontentDir . '/' . 'themes' ) !== 0 &&
                strpos( $this->wpvivid_replace_directory($directory->getRealPath()), $wpcontentDir . '/' . 'uploads' ) !== 0 )
        ) {
            return false;
        }

        return $path;
    }

    public function wpvivid_search_staging_theme_directories($wpvivid_staging_themes_dir){
        if ( empty( $wpvivid_staging_themes_dir ) ) {
            return false;
        }
        $found_themes = array();
        $wpvivid_staging_themes_dir = (array) $wpvivid_staging_themes_dir;
        foreach ( $wpvivid_staging_themes_dir as $theme_root ) {
            $dirs = @ scandir( $theme_root );
            if ( ! $dirs ) {
                continue;
            }
            foreach ( $dirs as $dir ) {
                if ( ! is_dir( $theme_root . '/' . $dir ) || $dir[0] == '.' || $dir == 'CVS' ) {
                    continue;
                }
                if ( file_exists( $theme_root . '/' . $dir . '/style.css' ) ) {
                    $found_themes[ $dir ] = array(
                        'theme_file' => $dir . '/style.css',
                        'theme_root' => $theme_root,
                    );
                }
                else {
                    $found_theme = false;
                    $sub_dirs = @ scandir( $theme_root . '/' . $dir );
                    if ( ! $sub_dirs ) {
                        continue;
                    }
                    foreach ( $sub_dirs as $sub_dir ) {
                        if ( ! is_dir( $theme_root . '/' . $dir . '/' . $sub_dir ) || $dir[0] == '.' || $dir == 'CVS' ) {
                            continue;
                        }
                        if ( ! file_exists( $theme_root . '/' . $dir . '/' . $sub_dir . '/style.css' ) ) {
                            continue;
                        }
                        $found_themes[ $dir . '/' . $sub_dir ] = array(
                            'theme_file' => $dir . '/' . $sub_dir . '/style.css',
                            'theme_root' => $theme_root,
                        );
                        $found_theme = true;
                    }
                    if ( ! $found_theme ) {
                        $found_themes[ $dir ] = array(
                            'theme_file' => $dir . '/style.css',
                            'theme_root' => $theme_root,
                        );
                    }
                }
            }
        }
        asort( $found_themes );
        return $found_themes;
    }

    public function get_staging_themes_info($wpvivid_staging_themes_dir){
        $themes = array();
        $theme_directories = $this->wpvivid_search_staging_theme_directories($wpvivid_staging_themes_dir);
        if ( !empty( $theme_directories ) ) {
            foreach ( $theme_directories as $theme => $theme_root ) {
                $themes[ $theme ] = $theme_root['theme_root'] . '/' . $theme;
                $themes[ $theme ] = new WP_Theme( $theme, $theme_root['theme_root'] );
            }
        }
        return $themes;
    }

    public function get_staging_plugins_info($wpvivid_stating_plugins_dir){
        $wp_plugins  = array();
        $plugin_root = $wpvivid_stating_plugins_dir;
        $plugins_dir  = @ opendir( $plugin_root );
        $plugin_files = array();
        if ( $plugins_dir ) {
            while ( ( $file = readdir( $plugins_dir ) ) !== false ) {
                if ( substr( $file, 0, 1 ) == '.' ) {
                    continue;
                }
                if ( is_dir( $plugin_root . '/' . $file ) ) {
                    $plugins_subdir = @ opendir( $plugin_root . '/' . $file );
                    if ( $plugins_subdir ) {
                        while ( ( $subfile = readdir( $plugins_subdir ) ) !== false ) {
                            if ( substr( $subfile, 0, 1 ) == '.' ) {
                                continue;
                            }
                            if ( substr( $subfile, -4 ) == '.php' ) {
                                $plugin_files[] = "$file/$subfile";
                            }
                        }
                        closedir( $plugins_subdir );
                    }
                } else {
                    if ( substr( $file, -4 ) == '.php' ) {
                        $plugin_files[] = $file;
                    }
                }
            }
            closedir( $plugins_dir );
        }
        if ( !empty( $plugin_files ) ) {
            foreach ( $plugin_files as $plugin_file ) {
                if ( ! is_readable( "$plugin_root/$plugin_file" ) ) {
                    continue;
                }

                $plugin_data = get_plugin_data( "$plugin_root/$plugin_file", false, false );

                if ( empty( $plugin_data['Name'] ) ) {
                    continue;
                }

                $wp_plugins[ plugin_basename( $plugin_file ) ] = $plugin_data;
            }
        }
        return $wp_plugins;
    }

    public function get_staging_directory_info($path){
        $wpcontentDir = $path.DIRECTORY_SEPARATOR.'wp-content';
        $wpcontentDir = str_replace('\\', '/', $wpcontentDir);
        $tmp_path = str_replace('\\', '/', $path);
        if(!file_exists($wpcontentDir)){
            //return error
        }
        else {
            $directories = new \DirectoryIterator($wpcontentDir);
        }
        $wpvivid_staging_themes_dir  = '';
        $wpvivid_stating_plugins_dir = '';
        foreach ( $directories as $directory ) {
            if( false === ($path = $this->getPath( $tmp_path, $wpcontentDir, $directory )) ) {
                continue;
            }
            if($directory == 'themes'){
                $wpvivid_staging_themes_dir  = $wpcontentDir . '/' . 'themes';
            }
            if($directory == 'plugins'){
                $wpvivid_stating_plugins_dir = $wpcontentDir . '/' . 'plugins';
            }
        }
        $ret['themes_list']  = $this->get_staging_themes_info($wpvivid_staging_themes_dir);
        $ret['plugins_list'] = $this->get_staging_plugins_info($wpvivid_stating_plugins_dir);
        return $ret;
    }

    public function get_theme_plugin_info($root)
    {
        $theme_info['size']=$this->get_folder_size($root,0);
        return $theme_info;
    }

    public function get_folder_size($root,$size)
    {
        $count = 0;
        if(is_dir($root))
        {
            $handler = opendir($root);
            if($handler!==false)
            {
                while (($filename = readdir($handler)) !== false)
                {
                    if ($filename != "." && $filename != "..") {
                        $count++;

                        if (is_dir($root . DIRECTORY_SEPARATOR . $filename))
                        {
                            $size=$this->get_folder_size($root . DIRECTORY_SEPARATOR . $filename,$size);
                        } else {
                            $size+=filesize($root . DIRECTORY_SEPARATOR . $filename);
                        }
                    }
                }
                if($handler)
                    @closedir($handler);
            }

        }
        return $size;
    }

    public function staging_site()
    {
        $redirect=false;
        if(is_multisite())
        {
            switch_to_blog(get_main_site_id());
            $staging_init=get_option('wpvivid_staging_init', false);
            $staging_finish=get_option('wpvivid_staging_finish', false);
            restore_current_blog();
        }
        else
        {
            $staging_init=get_option('wpvivid_staging_init', false);
            $staging_finish=get_option('wpvivid_staging_finish', false);
        }

        if($staging_finish)
        {
            delete_transient( 'wp_core_block_css_files' );

            if ( function_exists( 'save_mod_rewrite_rules' ) ) {
                save_mod_rewrite_rules();
            }
            else{
                if(file_exists(ABSPATH . 'wp-admin/includes/misc.php')) {
                    require_once ABSPATH . 'wp-admin/includes/misc.php';
                }
                if ( function_exists( 'save_mod_rewrite_rules' ) ) {
                    save_mod_rewrite_rules();
                }
            }
            flush_rewrite_rules(true);
            delete_option('wpvivid_staging_finish');
            if(!$this->check_theme_exist())
            {
                $redirect=true;
            }
        }

        if($staging_init)
        {
            global $wp_rewrite;

            if($staging_init == 1){
                //create staging site
                $wp_rewrite->set_permalink_structure( null );
            }
            else{
                //push to live site
                $wp_rewrite->set_permalink_structure( $staging_init );
            }

            delete_option('wpvivid_staging_init');
        }

        $data=$this->get_staging_site_data();

        if($data!==false)
        {
            $user = wp_get_current_user();
            if($user->exists())
            {
                wp_enqueue_style( "wpvivid-admin-bar", WPVIVID_PLUGIN_DIR_URL . "css/wpvivid-admin-bar.css", array(), WPVIVID_PLUGIN_VERSION );
            }

            if(!$this->is_login_page())
            {
                if(is_multisite())
                {
                    switch_to_blog(get_main_site_id());
                    $options=get_option('wpvivid_staging_options', false);
                    restore_current_blog();
                }
                else
                {
                    $options=get_option('wpvivid_staging_options',array());
                }

                $staging_not_need_login=isset($options['not_need_login']) ? $options['not_need_login'] : true;

                if(!$staging_not_need_login)
                {
                    if(!current_user_can('manage_options'))
                    {
                        $this->output_login_page();
                    }
                }
            }
        }

        if($redirect)
        {
            ?>
            <script>
                location.reload();
            </script>
            <?php
        }
    }

    public function check_theme_exist()
    {
        global $wp_theme_directories;
        $stylesheet = get_stylesheet();
        $theme_root = get_raw_theme_root( $stylesheet );
        if ( false === $theme_root ) {
            $theme_root = WP_CONTENT_DIR . '/themes';
        }
        elseif ( ! in_array( $theme_root, (array) $wp_theme_directories ) )
        {
            $theme_root = WP_CONTENT_DIR . $theme_root;
        }

        $theme_dir = $stylesheet;

        // Correct a situation where the theme is 'some-directory/some-theme' but 'some-directory' was passed in as part of the theme root instead.
        if ( ! in_array( $theme_root, (array) $wp_theme_directories ) && in_array( dirname( $theme_root ), (array) $wp_theme_directories ) ) {
            $stylesheet = basename( $theme_root ) . '/' .$theme_dir;
            $theme_root = dirname( $theme_root );
        }

        $theme_file       = $stylesheet . '/style.css';

        if( ! file_exists( $theme_root . '/' . $theme_file ) )
        {
            $themes=wp_get_themes();
            foreach ($themes as $theme)
            {
                switch_theme($theme->get_stylesheet());
                return false;
            }
        }
        return true;
    }

    public function is_login_page()
    {
        return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
    }

    public function wpvivid_logout_redirect()
    {
        $redirectTo = get_site_url();
        wp_logout();
        ?>
        <script>
            location.href='<?php echo esc_url($redirectTo); ?>';
        </script>
        <?php
    }

    public function output_login_page()
    {
        if(is_user_logged_in())
        {
            if(current_user_can( 'manage_options' ))
            {
                return false;
            }
            else
            {
                $this->wpvivid_logout_redirect();
            }
        }
        if( !isset( $_POST['log'] ) || !isset( $_POST['pwd'] ) )
        {

        }
        else
        {
            $user_data = get_user_by( 'login', sanitize_user($_POST['log']) );
            if( !$user_data )
            {
                $user_data = get_user_by( 'email', sanitize_email($_POST['log']) );
                $log=sanitize_email($_POST['log']);
            }
            else
            {
                $log=sanitize_user($_POST['log']);
            }

            if( $user_data )
            {
                if( wp_check_password( $_POST['pwd'], $user_data->user_pass, $user_data->ID ) )
                {

                    $rememberme = isset( $_POST['rememberme'] ) ? true : false;

                    wp_set_auth_cookie( $user_data->ID, $rememberme );
                    wp_set_current_user( $user_data->ID, $log );
                    do_action( 'wp_login', $log, get_userdata( $user_data->ID ) );

                    $redirect_to = get_site_url() . '/wp-admin/';

                    if( !empty( $_POST['redirect_to'] ) )
                    {
                        $url=sanitize_url($_POST['redirect_to']);
                        $redirectTo = wp_safe_redirect($url);
                    }

                    header( 'Location:' . $redirectTo );
                }
            }
        }

        require_once( ABSPATH . 'wp-login.php' );

        ?>
        <script>
            jQuery(document).ready(function ()
            {
                jQuery('#loginform').prop('action', '');
            });
        </script>
        <?php

        die();
    }

    public function wpvivid_get_staging_database_object($use_additional_db, $db_user, $db_pass, $db_name, $db_host){
        if($use_additional_db){
            return new wpdb($db_user, $db_pass, $db_name, $db_host);
        }
        else{
            global $wpdb;
            return $wpdb;
        }
    }

    /** Delete only server-registered staging resources; keep the task on any failure. */
    public function _delete_site($site_id, $unfinished = false, $cancel_only = false)
    {
        $lock = false;
        try
        {
            $task = $this->get_staging_task($site_id);
            $lock = $this->acquire_staging_lock($site_id);
            if ($lock === false) throw new Exception('The staging task is still running. Please retry cleanup.');
            $task->get_task();
            if ($cancel_only && $task->task['status']['str'] !== 'cancel')
                throw new Exception('Only a cancelled staging task can be cleaned by this endpoint.');
            if (in_array($task->task['status']['str'], array('ready', 'running'), true))
                throw new Exception('The staging task must stop before it can be deleted.');
            if ($unfinished && ($task->is_restore() || $task->is_copy()))
            {
                if ($cancel_only) throw new Exception('This is not a cancelled new staging task.');
                return array('result' => 'success');
            }
            if ($unfinished)
            {
                $path = isset($task->task['path']['des_path']) ? $task->task['path']['des_path'] : '';
                $connection = isset($task->task['db_connect']) ? $task->task['db_connect'] : array();
            }
            else
            {
                if (empty($task->task['site']['path']) || empty($task->task['site']['prefix']))
                    throw new Exception('The registered staging site information is missing.');
                $path = $task->task['site']['path'];
                $site_db = $task->task['site']['db_connect'];
                $connection = array('new_prefix' => $task->task['site']['prefix'],
                    'des_use_additional_db' => !empty($site_db['use_additional_db']));
                if ($connection['des_use_additional_db'])
                {
                    foreach (array('dbhost', 'dbname', 'dbuser', 'dbpassword') as $key)
                        $connection['des_' . $key] = $site_db[$key];
                }
            }
            $path = $this->validate_staging_target_path($path);
            $prefix = $this->validate_staging_prefix(isset($connection['new_prefix']) ? $connection['new_prefix'] : '');
            $db = $this->staging_database($connection);
            $all_tables = $this->staging_database_tables($db);
            $candidates = array();
            foreach ($all_tables as $name)
            {
                if (strpos($name, $prefix) === 0) $candidates[] = $name;
            }
            if (!empty($task->task['cleanup']))
            {
                $scope = $task->task['cleanup'];
                if ($scope['path'] !== $this->normalize_staging_path($path) ||
                    $scope['prefix'] !== $prefix ||
                    $scope['connection_hash'] !== $this->staging_connection_hash($connection))
                    throw new Exception('The staging cleanup destination does not match the task.');
                foreach ($candidates as $name)
                {
                    if (in_array($name, $scope['preexisting_tables'], true))
                        throw new Exception('Cleanup would affect a pre-existing database table.');
                }
                if ($unfinished)
                {
                    // Files may be absent, but cancellation is still bound to the task table manifest.
                    foreach ($candidates as $name)
                    {
                        if (!in_array($name, $scope['tables'], true))
                            throw new Exception('A database table is not registered to this staging task.');
                    }
                }
            }
            else
            {
                if ($unfinished)
                    throw new Exception('This older task has no verified cleanup information. Automatic cleanup was refused.');
                // Preserve deletion of older completed sites only when their prefix is clearly separate.
                global $wpdb;
                foreach (array($wpdb->prefix, $wpdb->base_prefix) as $live_prefix)
                {
                    if (strpos($prefix, $live_prefix) === 0 || strpos($live_prefix, $prefix) === 0)
                        throw new Exception('The staging table prefix overlaps the live site.');
                }
                $live_tables = $wpdb->tables('all', true);
                if (array_intersect($candidates, array_values($live_tables)))
                    throw new Exception('Cleanup would affect a live database table.');
            }

            // All path, connection and table checks above complete before the first deletion.
            if (!empty($candidates))
            {
                if ($db->query('SET foreign_key_checks = 0') === false)
                    throw new Exception('Failed to prepare staging database cleanup.');
                try
                {
                    foreach ($candidates as $name)
                    {
                        $identifier = '`' . str_replace('`', '``', $name) . '`';
                        if ($db->query('DROP TABLE IF EXISTS ' . $identifier) === false)
                            throw new Exception('Failed to delete a staging database table.');
                    }
                }
                finally
                {
                    $db->query('SET foreign_key_checks = 1');
                }
            }
            if (is_dir($path))
            {
                if (!class_exists('WP_Filesystem_Base')) include_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
                if (!class_exists('WP_Filesystem_Direct')) include_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
                $fs = new WP_Filesystem_Direct(false);
                if (!$fs->rmdir($path, true)) throw new Exception('Failed to delete the staging directory.');
            }
            $tasks = get_option('wpvivid_staging_task_list', array());
            unset($tasks[$site_id]);
            update_option('wpvivid_staging_task_list', $tasks, 'no');
            if (get_option('wpvivid_current_running_staging_task', '') === $site_id)
            {
                update_option('wpvivid_current_running_staging_task', '', 'no');
                update_option('wpvivid_staging_task_cancel', false, 'no');
            }
            return array('result' => 'success');
        }
        catch (Exception $error)
        {
            return array('result' => 'failed', 'error' => $error->getMessage());
        }
        finally
        {
            $this->release_staging_lock($lock);
        }
    }

    public function get_recent_post()
    {
        //set_prefix
        $post_type='post';
        $args = array(
            'orderby' => 'modified',
            'ignore_sticky_posts' => '1',
            'page_id' => 0,
            'posts_per_page' => 1,
            'post_type' => $post_type
        );

        $loop = new WP_Query( $args );
        echo '<ul>';
        while( $loop->have_posts())
        {
            $loop->the_post();
            echo '<li><a href="' . esc_url(get_permalink( $loop->post->ID )) . '"> ' .esc_html(get_the_title( $loop->post->ID ) ). '</a> ( '. esc_url(get_the_modified_date()) .') </li>';
        }
        echo '</ul>';
        echo'<input id="wpvivid_update_post" type="button" class="button button-primary" value="Update">';

    }

    public function deal_shutdown_error($task_id)
    {
        if($this->end_shutdown_function===false)
        {
            $task=false;

            $last_error = error_get_last();
            if (!empty($last_error) && !in_array($last_error['type'], array(E_NOTICE,E_WARNING,E_USER_NOTICE,E_USER_WARNING,E_DEPRECATED), true))
            {
                $error = $last_error;
            } else {
                $error = false;
            }

            try
            {
                $task=new WPvivid_Staging_Task($task_id);
                if($error==false)
                {
                    $message='Create staging site end with a error.';
                }
                else
                {
                    $message=$error;
                }
                $task->finished_task_with_error($message);
                $this->log->WriteLog($message,'error');
            }
            catch (Exception $error)
            {
                $message = 'An Error has occurred. class:'.get_class($error).';msg:'.$error->getMessage().';code:'.$error->getCode().';line:'.$error->getLine().';in_file:'.$error->getFile().';';
                error_log($message);
                if($task!==false)
                    $task->finished_task_with_error($message);
                $this->log->WriteLog($message,'error');
            }

            die();
        }
    }

    /**
     * Create a staging task without copying files or database tables.
     * @throws \Exception
     */
    public function create_staging_task()
    {
        $lock = $this->acquire_staging_lock('create');
        if ($lock === false) throw new Exception('A staging task is being created. Please wait.');
        $task = false;
        try
        {
            $tasks = get_option('wpvivid_staging_task_list', array());
            $current = get_option('wpvivid_current_running_staging_task', '');
            if ($current !== '' && isset($tasks[$current]) && $tasks[$current]['status']['str'] !== 'completed')
                throw new Exception('The current staging task must finish or be cleaned before creating another.');
            foreach ($tasks as $record)
            {
                if (isset($record['status']['str']) &&
                    in_array($record['status']['str'], array('ready', 'running', 'cancel'), true))
                    throw new Exception('Another staging task is active or awaiting cleanup.');
            }
            foreach (array('path', 'table_prefix', 'custom_dir', 'additional_db') as $key)
            {
                if (!isset($_POST[$key]) || !is_string($_POST[$key]))
                    throw new Exception('Invalid staging creation request.');
            }
            $staging_options = json_decode(wp_unslash($_POST['custom_dir']), true);
            $additional_db_options = json_decode(wp_unslash($_POST['additional_db']), true);
            if (!is_array($staging_options) || !is_array($additional_db_options))
                throw new Exception('Invalid staging options.');
            $directory = $this->get_validated_staging_directory();
            $des_path = $this->validate_staging_target_path($directory['path']);
            if (file_exists($des_path)) throw new Exception('The staging directory already exists.');
            $table_prefix = $this->validate_staging_prefix(wp_unslash($_POST['table_prefix']));
            $option = array('options' => $this->set_staging_option(), 'data' => array());
            $option['data']['path'] = array('src_path' => untrailingslashit(ABSPATH), 'des_path' => $des_path);
            $url_path = $directory['name'];
            if ($_POST['root_dir'] === '1')
                $url_path = str_replace(ABSPATH, '', WP_CONTENT_DIR) . '/' . $url_path;
            $new_site_url = untrailingslashit($this->get_database_site_url()) . '/' . $url_path;
            $new_home_url = untrailingslashit($this->get_database_home_url()) . '/' . $url_path;
            $option['data']['restore'] = false;
            $option['data']['copy'] = false;
            $this->set_create_staging_option($option, $staging_options, $additional_db_options,
                $new_site_url, $new_home_url, $table_prefix);
            if (empty($option['data']['db_connect']))
                throw new Exception('Staging database options are required.');
            $connection = $option['data']['db_connect'];
            $db = $this->staging_database($connection);
            $preexisting = $this->staging_database_tables($db);
            // Check the actual destination connection, even when another account/host alias was supplied.
            foreach ($preexisting as $name)
            {
                if (strpos($name, $table_prefix) === 0)
                    throw new Exception('The staging table prefix is already in use.');
            }

            $task = new WPvivid_Staging_Task();
            $task->setup_task($option);
            $task->task['cleanup'] = array(
                'version' => 1,
                'path' => $this->normalize_staging_path($des_path),
                'prefix' => $table_prefix,
                'connection_hash' => $this->staging_connection_hash($connection),
                'preexisting_tables' => $preexisting,
                'tables' => array()
            );
            $task->update_task();
            $task->update_action_time('create_time');
            update_option('wpvivid_staging_task_cancel', false, 'no');
            update_option('wpvivid_current_running_staging_task', $task->get_id(), 'no');
            $this->log->CreateLogFile($task->get_log_file_name(), 'no_folder', 'staging');
            $this->log->WriteLog('Staging task created. Waiting to execute.', 'notice');
            return array('result' => 'success', 'task_id' => $task->get_id());
        }
        catch (Exception $error)
        {
            // No worker has started, so only remove the incomplete task registration.
            if ($task instanceof WPvivid_Staging_Task)
            {
                $tasks = get_option('wpvivid_staging_task_list', array());
                unset($tasks[$task->get_id()]);
                update_option('wpvivid_staging_task_list', $tasks, 'no');
                if (get_option('wpvivid_current_running_staging_task', '') === $task->get_id())
                    update_option('wpvivid_current_running_staging_task', '', 'no');
            }
            throw $error;
        }
        finally
        {
            $this->release_staging_lock($lock);
        }
    }

    /**
     * Execute one existing task slice; never creates a task.
     * @throws \Exception
     */
    public function run_staging_task($task_id)
    {
        $task = $this->get_staging_task($task_id);
        $current = get_option('wpvivid_current_running_staging_task', '');
        if ($current !== $task_id) throw new Exception('This is not the current staging task.');
        $lock = $this->acquire_staging_lock($task_id);
        if ($lock === false)
            return array('result' => 'success', 'task_id' => $task_id, 'busy' => true);
        try
        {
            $task->get_task();
            $this->validate_staging_task_scope($task);
            if ($task->get_status() === 'cancel')
            {
                $task->task['status']['str'] = 'cancel';
                $task->update_task();
                update_option('wpvivid_staging_task_cancel', false, 'no');
                return array('result' => 'success', 'task_id' => $task_id, 'cancelled' => true);
            }
            if ($task->task['status']['str'] !== 'ready')
                return array('result' => 'success', 'task_id' => $task_id);
            $this->log->OpenLogFile($task->get_log_file_name());
            $task->set_memory_limit();
            $task->set_time_limit();
            $this->end_shutdown_function = false;
            register_shutdown_function(array($this, 'deal_staging_shutdown_error'));
            register_shutdown_function(array($this, 'deal_shutdown_error'), $task_id);
            try
            {
                $doing = $task->get_doing_task();
                if ($doing === false) $doing = $task->get_start_next_task();
                $success = $task->do_task($doing);
                $this->capture_staging_tables($task_id);
                $task->get_task();
                if (get_option('wpvivid_staging_task_cancel', false))
                {
                    $task->task['status']['str'] = 'cancel';
                    $task->task['status']['error'] = 'Task cancelled.';
                    $task->update_task();
                    update_option('wpvivid_staging_task_cancel', false, 'no');
                }
                else if (!$success)
                {
                    $task->finished_task_with_error();
                }
                else if ($task->get_start_next_task() === false)
                {
                    $this->log->WriteLog('Creating staging site is completed.', 'notice');
                    $task->finished_task();
                }
            }
            catch (Exception $error)
            {
                $task->finished_task_with_error($error->getMessage());
                $this->log->WriteLog($error->getMessage(), 'error');
                if (!$task->response_sent)
                    return array('result' => 'failed', 'task_id' => $task_id, 'error' => $error->getMessage());
            }
            finally
            {
                $this->end_shutdown_function = true;
            }
            return $task->response_sent ? false : array('result' => 'success', 'task_id' => $task_id);
        }
        finally
        {
            $this->release_staging_lock($lock);
        }
    }

    public function set_db_connect_option($new_site_url,$new_home_url,$additional_db_options,$table_prefix,$mu_single_site_id='')
    {
        global $wpdb;
        if(!empty($mu_single_site_id))
        {
            $prefix=$wpdb->get_blog_prefix($mu_single_site_id);
            $db_connect['old_prefix'] = $prefix;
            $db_connect['old_site_url'] = get_site_url($mu_single_site_id);
            $db_connect['old_home_url'] = get_home_url($mu_single_site_id);
        }
        else
        {
            $db_connect['old_prefix'] = $wpdb->base_prefix;
            $db_connect['old_site_url'] = untrailingslashit($this->get_database_site_url());
            $db_connect['old_home_url'] = untrailingslashit($this->get_database_home_url());
        }

        $db_connect['new_site_url'] = $new_site_url;
        $db_connect['new_home_url'] = $new_home_url;
        $db_connect['src_use_additional_db'] = false;
        $db_connect['des_use_additional_db'] = false;
        $db_connect['new_prefix'] = $table_prefix;
        if(isset($additional_db_options['additional_database_check']) && $additional_db_options['additional_database_check'] == '1')
        {
            /*$option['data']['db_connect']['des_use_additional_db'] = true;
            $option['data']['db_connect']['des_dbuser'] = $additional_db_options['additional_database_info']['db_user'];
            $option['data']['db_connect']['des_dbpassword'] = $additional_db_options['additional_database_info']['db_pass'];
            $option['data']['db_connect']['des_dbname'] = $additional_db_options['additional_database_info']['db_name'];
            $option['data']['db_connect']['des_dbhost'] = $additional_db_options['additional_database_info']['db_host'];*/
            $db_connect['des_use_additional_db'] = true;
            $db_connect['des_dbuser'] = $additional_db_options['additional_database_info']['db_user'];
            $db_connect['des_dbpassword'] = $additional_db_options['additional_database_info']['db_pass'];
            $db_connect['des_dbname'] = $additional_db_options['additional_database_info']['db_name'];
            $db_connect['des_dbhost'] = $additional_db_options['additional_database_info']['db_host'];
        }

        return $db_connect;
    }

    public function set_create_staging_option(&$option,$staging_options,$additional_db_options,$new_site_url,$new_home_url,$table_prefix)
    {
        global $wpdb;

        if(isset($_POST['create_new_wp']))
        {
            $option['data']['core'] = true;

            if($staging_options['themes_check'] == '1')
            {
                $option['data']['theme']['exclude_regex'] = array();
                foreach ($staging_options['themes_list'] as $theme)
                {
                    $option['data']['theme']['exclude_regex'][] = '#^'.preg_quote($this -> transfer_path(get_theme_root().DIRECTORY_SEPARATOR.$theme), '/').'$#';
                }
            }

            if($staging_options['plugins_check'] == '1')
            {
                $option['data']['plugins']['exclude_regex'] = array();
                foreach ($staging_options['plugins_list'] as $plugin)
                {
                    $option['data']['plugins']['exclude_regex'][] = '#^'.preg_quote($this -> transfer_path(WP_PLUGIN_DIR.DIRECTORY_SEPARATOR.$plugin), '/').'#';
                }
            }

            $option['data']['db_connect']=$this->set_db_connect_option($new_site_url,$new_home_url,$additional_db_options,$table_prefix);
            $option['data']['create_new_wp']=true;
            //$option['data']['db']['exclude_tables'] = array();
            //$option['data']['db']['exclude_tables'][] = $wpdb->base_prefix.'hw_blocks';
            //foreach ($staging_options['database_list'] as $table)
            //{
            //    $option['data']['db']['exclude_tables'][] = $table;
            //}
        }
        else
        {
            if($staging_options['database_check'] == '1')
            {
                $option['data']['db_connect']=$this->set_db_connect_option($new_site_url,$new_home_url,$additional_db_options,$table_prefix);

                $option['data']['db']['exclude_tables'] = array();
                $option['data']['db']['exclude_tables'][] = $wpdb->base_prefix.'hw_blocks';
                foreach ($staging_options['database_list'] as $table)
                {
                    $option['data']['db']['exclude_tables'][] = $table;
                }
            }

            $option['data']['theme']['exclude_regex'] = array();
            if($staging_options['themes_check'] == '1')
            {
                foreach ($staging_options['themes_list'] as $key => $value)
                {
                    $option['data']['theme']['exclude_regex'][] = '#^'.preg_quote($this -> transfer_path(get_theme_root().DIRECTORY_SEPARATOR.$key), '/').'#';
                }
                $option['data']['theme']['exclude_files_regex']=array();
                $theme_extension_tmp = array();
                if(isset($staging_options['themes_extension']) && !empty($staging_options['themes_extension']))
                {
                    $str_tmp = explode(',', $staging_options['themes_extension']);
                    for($index=0; $index<count($str_tmp); $index++)
                    {
                        if(!empty($str_tmp[$index]))
                        {
                            $option['data']['theme']['exclude_files_regex'][] = '#' . '.*\.' . $str_tmp[$index] . '$' . '#';
                            $theme_extension_tmp[] = $str_tmp[$index];
                        }
                    }
                    $staging_options['themes_extension'] = $theme_extension_tmp;
                }
            }

            $option['data']['plugins']['exclude_regex'] = array();
            if($staging_options['plugins_check'] == '1')
            {
                foreach ($staging_options['plugins_list'] as $key => $value)
                {
                    $option['data']['plugins']['exclude_regex'][] = '#^'.preg_quote($this -> transfer_path(WP_PLUGIN_DIR.DIRECTORY_SEPARATOR.$key), '/').'#';
                }
                $option['data']['plugins']['exclude_files_regex']=array();
                $plugin_extension_tmp = array();
                if(isset($staging_options['plugins_extension']) && !empty($staging_options['plugins_extension']))
                {
                    $str_tmp = explode(',', $staging_options['plugins_extension']);
                    for($index=0; $index<count($str_tmp); $index++)
                    {
                        if(!empty($str_tmp[$index]))
                        {
                            $option['data']['plugins']['exclude_files_regex'][] = '#' . '.*\.' . $str_tmp[$index] . '$' . '#';
                            $plugin_extension_tmp[] = $str_tmp[$index];
                        }
                    }
                    $staging_options['plugins_extension'] = $plugin_extension_tmp;
                }
            }

            if($staging_options['uploads_check'] == '1')
            {
                $upload_dir = wp_upload_dir();
                $option['data']['upload']['exclude_regex'] = array();
                foreach ($staging_options['uploads_list'] as $key => $value)
                {
                    $option['data']['upload']['exclude_regex'][] = '#^'.preg_quote($this -> transfer_path($upload_dir['basedir'].DIRECTORY_SEPARATOR.$key), '/').'#';
                }
                $option['data']['upload']['exclude_files_regex']=array();
                $upload_extension_tmp = array();
                if(isset($staging_options['upload_extension']) && !empty($staging_options['upload_extension']))
                {
                    $str_tmp = explode(',', $staging_options['upload_extension']);
                    for($index=0; $index<count($str_tmp); $index++)
                    {
                        if(!empty($str_tmp[$index]))
                        {
                            $option['data']['upload']['exclude_files_regex'][] = '#' . '.*\.' . $str_tmp[$index] . '$' . '#';
                            $upload_extension_tmp[] = $str_tmp[$index];
                        }
                    }
                    $staging_options['upload_extension'] = $upload_extension_tmp;
                }
            }

            if($staging_options['content_check'] == '1')
            {
                $option['data']['wp-content']['exclude_regex'] = array();
                foreach ($staging_options['content_list'] as $key => $value)
                {
                    $option['data']['wp-content']['exclude_regex'][] = '#^'.preg_quote($this -> transfer_path(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$key), '/').'#';
                }
                $option['data']['wp-content']['exclude_files_regex']=array();
                $content_extension_tmp = array();
                if(isset($staging_options['content_extension']) && !empty($staging_options['content_extension']))
                {
                    $str_tmp = explode(',', $staging_options['content_extension']);
                    for($index=0; $index<count($str_tmp); $index++)
                    {
                        if(!empty($str_tmp[$index]))
                        {
                            $option['data']['wp-content']['exclude_files_regex'][] = '#' . '.*\.' . $str_tmp[$index] . '$' . '#';
                            $content_extension_tmp[] = $str_tmp[$index];
                        }
                    }
                    $staging_options['content_extension'] = $content_extension_tmp;
                }
            }

            if($staging_options['core_check'] == '1')
            {
                $option['data']['core'] = true;
            }

            if($staging_options['additional_file_check'] == '1')
            {
                $custom['exclude_regex'] = array();
                $custom['exclude_files_regex']=array();
                $additional_file_extension_tmp = array();
                if(isset($staging_options['additional_file_extension']) && !empty($staging_options['additional_file_extension']))
                {
                    $str_tmp = explode(',', $staging_options['additional_file_extension']);
                    for($index=0; $index<count($str_tmp); $index++)
                    {
                        if(!empty($str_tmp[$index]))
                        {
                            $custom['exclude_files_regex'][] = '#' . '.*\.' . $str_tmp[$index] . '$' . '#';
                            $additional_file_extension_tmp[] = $str_tmp[$index];
                        }
                    }
                    $staging_options['additional_file_extension'] = $additional_file_extension_tmp;
                }
                foreach ($staging_options['additional_file_list'] as $key => $value)
                {
                    $custom['root'] = $key;
                    $option['data']['custom'][] = $custom;
                }
            }

            self::wpvivid_set_staging_history($staging_options);
        }
    }

    public function get_table_list($prefix,&$mu_exclude_table,$task=false,$exculude_user=true)
    {

        global $wpdb;

        if($task===false)
        {
            $db=$wpdb;
        }
        else
        {
            $db=$task->get_site_db_instance();
        }

        $sql=$db->prepare("SHOW TABLES LIKE %s;", $wpdb->esc_like($prefix) . '%');
        $result = $db->get_results($sql, OBJECT_K);
        foreach ($result as $table_name=>$value)
        {
            if($prefix==$db->base_prefix)
            {
                if ( 1 == preg_match('/^' . $db->base_prefix . '\d+_/', $table_name) )
                {

                }
                else
                {
                    if($table_name==$db->base_prefix.'blogs'&&$exculude_user!==false)
                        continue;
                    if($exculude_user===false)
                    {
                        if($table_name==$db->base_prefix.'users'||$table_name==$db->base_prefix.'usermeta')
                            continue;
                    }
                    $mu_exclude_table[]=$table_name;
                }
            }
            else
            {
                $mu_exclude_table[]=$table_name;
            }
        }
    }

    public function get_upload_exclude_folder($site_id,$des=false,$task=false)
    {
        if($des)
        {
            $upload_dir = wp_upload_dir();
            $dir = str_replace( ABSPATH, '', $upload_dir['basedir'] );
            $src_path=$task->get_site_path();
            $upload_basedir=$src_path.DIRECTORY_SEPARATOR.$dir;
            if ( defined( 'MULTISITE' ) )
            {
                $upload_basedir = $upload_basedir.'/sites/' . $site_id;
            } else {
                $upload_basedir = $upload_basedir.'/' . $site_id;
            }
            return $upload_basedir;
        }
        else
        {
            $upload= $this->get_site_upload_dir($site_id);
            return $upload['basedir'];
        }
    }

    public function get_site_upload_dir($site_id, $time = null, $create_dir = true, $refresh_cache = false)
    {
        static $cache = array(), $tested_paths = array();

        $key = sprintf( '%d-%s',$site_id, (string) $time );

        if ( $refresh_cache || empty( $cache[ $key ] ) ) {
            $cache[ $key ] = $this->_wp_upload_dir( $site_id,$time );
        }

        /**
         * Filters the uploads directory data.
         *
         * @since 2.0.0
         *
         * @param array $uploads Array of upload directory data with keys of 'path',
         *                       'url', 'subdir, 'basedir', and 'error'.
         */
        $uploads = apply_filters( 'upload_dir', $cache[ $key ] );

        if ( $create_dir ) {
            $path = $uploads['path'];

            if ( array_key_exists( $path, $tested_paths ) ) {
                $uploads['error'] = $tested_paths[ $path ];
            } else {
                if ( ! wp_mkdir_p( $path ) ) {
                    if ( 0 === strpos( $uploads['basedir'], ABSPATH ) ) {
                        $error_path = str_replace( ABSPATH, '', $uploads['basedir'] ) . $uploads['subdir'];
                    } else {
                        $error_path = basename( $uploads['basedir'] ) . $uploads['subdir'];
                    }

                    $uploads['error'] = sprintf(
                    /* translators: %s: directory path */
                        __( 'Unable to create directory %s. Is its parent directory writable by the server?', 'wpvivid-backuprestore' ),
                        esc_html( $error_path )
                    );
                }

                $tested_paths[ $path ] = $uploads['error'];
            }
        }

        return $uploads;
    }

    public function _wp_upload_dir($site_id, $time = null ) {
        $siteurl     = get_option( 'siteurl' );
        $upload_path = trim( get_option( 'upload_path' ) );

        if ( empty( $upload_path ) || 'wp-content/uploads' == $upload_path ) {
            $dir = WP_CONTENT_DIR . '/uploads';
        } elseif ( 0 !== strpos( $upload_path, ABSPATH ) ) {
            // $dir is absolute, $upload_path is (maybe) relative to ABSPATH
            $dir = path_join( ABSPATH, $upload_path );
        } else {
            $dir = $upload_path;
        }

        if ( ! $url = get_option( 'upload_url_path' ) ) {
            if ( empty( $upload_path ) || ( 'wp-content/uploads' == $upload_path ) || ( $upload_path == $dir ) ) {
                $url = WP_CONTENT_URL . '/uploads';
            } else {
                $url = trailingslashit( $siteurl ) . $upload_path;
            }
        }

        /*
         * Honor the value of UPLOADS. This happens as long as ms-files rewriting is disabled.
         * We also sometimes obey UPLOADS when rewriting is enabled -- see the next block.
         */
        if ( defined( 'UPLOADS' ) && ! ( is_multisite() && get_site_option( 'ms_files_rewriting' ) ) ) {
            $dir = ABSPATH . UPLOADS;
            $url = trailingslashit( $siteurl ) . UPLOADS;
        }

        // If multisite (and if not the main site in a post-MU network)
        if ( is_multisite() && ! ( is_main_network() && is_main_site($site_id) && defined( 'MULTISITE' ) ) ) {
            if ( ! get_site_option( 'ms_files_rewriting' ) ) {
                /*
                 * If ms-files rewriting is disabled (networks created post-3.5), it is fairly
                 * straightforward: Append sites/%d if we're not on the main site (for post-MU
                 * networks). (The extra directory prevents a four-digit ID from conflicting with
                 * a year-based directory for the main site. But if a MU-era network has disabled
                 * ms-files rewriting manually, they don't need the extra directory, as they never
                 * had wp-content/uploads for the main site.)
                 */

                if ( defined( 'MULTISITE' ) ) {
                    $ms_dir = '/sites/' . $site_id;
                } else {
                    $ms_dir = '/' . $site_id;
                }

                $dir .= $ms_dir;
                $url .= $ms_dir;
            } elseif ( defined( 'UPLOADS' ) && ! ms_is_switched() ) {
                /*
                 * Handle the old-form ms-files.php rewriting if the network still has that enabled.
                 * When ms-files rewriting is enabled, then we only listen to UPLOADS when:
                 * 1) We are not on the main site in a post-MU network, as wp-content/uploads is used
                 *    there, and
                 * 2) We are not switched, as ms_upload_constants() hardcodes these constants to reflect
                 *    the original blog ID.
                 *
                 * Rather than UPLOADS, we actually use BLOGUPLOADDIR if it is set, as it is absolute.
                 * (And it will be set, see ms_upload_constants().) Otherwise, UPLOADS can be used, as
                 * as it is relative to ABSPATH. For the final piece: when UPLOADS is used with ms-files
                 * rewriting in multisite, the resulting URL is /files. (#WP22702 for background.)
                 */

                if ( defined( 'BLOGUPLOADDIR' ) ) {
                    $dir = untrailingslashit( BLOGUPLOADDIR );
                } else {
                    $dir = ABSPATH . UPLOADS;
                }
                $url = trailingslashit( $siteurl ) . 'files';
            }
        }

        $basedir = $dir;
        $baseurl = $url;

        $subdir = '';
        if ( get_option( 'uploads_use_yearmonth_folders' ) ) {
            // Generate the yearly and monthly dirs
            if ( ! $time ) {
                $time = current_time( 'mysql' );
            }
            $y      = substr( $time, 0, 4 );
            $m      = substr( $time, 5, 2 );
            $subdir = "/$y/$m";
        }

        $dir .= $subdir;
        $url .= $subdir;

        return array(
            'path'    => $dir,
            'url'     => $url,
            'subdir'  => $subdir,
            'basedir' => $basedir,
            'baseurl' => $baseurl,
            'error'   => false,
        );
    }

    public function deal_staging_shutdown_error()
    {
        if($this->end_shutdown_function===false)
        {
            $last_error = error_get_last();
            if (!empty($last_error) && !in_array($last_error['type'], array(E_NOTICE,E_WARNING,E_USER_NOTICE,E_USER_WARNING,E_DEPRECATED), true))
            {
                $error = $last_error;
            } else {
                $error = false;
            }

            if ($error === false)
            {
                $error = 'Task interrupted. Please hold on. We are starting a retry.';
            } else {
                $error = 'type: '. $error['type'] . ', ' . $error['message'] . ' file:' . $error['file'] . ' line:' . $error['line'];
                error_log($error);
            }
            $this->log->WriteLog($error,'error');

            $list = get_option('wpvivid_staging_task_list',array());
            if(!empty($list))
            {
                foreach ($list as $key => $value)
                {
                    if($value['status']['str'] === 'running')
                    {
                        $list[$key]['status']['str']='ready';
                        update_option('wpvivid_staging_task_list', $list, 'no');
                        break;
                    }
                }
            }

            die();
        }
    }

    public static function wpvivid_set_staging_history($option){
        update_option('wpvivid_staging_history', $option, 'no');
    }

    public static function wpvivid_get_staging_history(){
        $options = get_option('wpvivid_staging_history', array());
        return $options;
    }

    public static function wpvivid_get_push_staging_history(){
        $options = get_option('wpvivid_push_staging_history', array());
        return $options;
    }

    private function transfer_path($path)
    {
        $path = str_replace('\\','/',$path);
        $values = explode('/',$path);
        return implode(DIRECTORY_SEPARATOR,$values);
    }

    public function set_staging_option()
    {
        $options=get_option('wpvivid_staging_options');

        if(isset($options['staging_db_insert_count']))
            $option['staging_db_insert_count']=$options['staging_db_insert_count'];
        else
            $option['staging_db_insert_count']=10000;

        if(isset($options['staging_db_replace_count']))
            $option['staging_db_replace_count']=$options['staging_db_replace_count'];
        else
            $option['staging_db_replace_count']=5000;

        if(isset($options['staging_memory_limit']))
            $option['staging_memory_limit']=$options['staging_memory_limit'];
        else
            $option['staging_memory_limit']='256M';

        if(isset($options['staging_file_copy_count']))
            $option['staging_file_copy_count']=$options['staging_file_copy_count'];
        else
            $option['staging_file_copy_count']=500;

        if(isset($options['staging_exclude_file_size'])) {
            $option['staging_exclude_file_size'] = $options['staging_exclude_file_size'];
        }
        else {
            $option['staging_exclude_file_size'] = 30;
        }

        if(isset($options['staging_max_execution_time']))
            $option['staging_max_execution_time']=$options['staging_max_execution_time'];
        else
            $option['staging_max_execution_time']=900;

        if(isset($options['staging_resume_count']))
            $option['staging_resume_count']=$options['staging_resume_count'];
        else
            $option['staging_resume_count']=6;

        if(isset($options['staging_overwrite_permalink']))
            $option['staging_overwrite_permalink']=$options['staging_overwrite_permalink'];
        else
            $option['staging_overwrite_permalink']=1;

        return $option;
    }

    public function export_setting_addon($json)
    {
        $default = array();
        $wpvivid_staging_history = get_option('wpvivid_staging_history', $default);
        $wpvivid_push_staging_history = get_option('wpvivid_push_staging_history', $default);
        $json['data']['wpvivid_staging_history'] = $wpvivid_staging_history;
        $json['data']['wpvivid_push_staging_history'] = $wpvivid_push_staging_history;
        return $json;
    }

    /**
     * Validate the requested staging directory.
     *
     * @return array
     * @throws Exception
     */
    public function get_validated_staging_directory()
    {
        if (!isset($_POST['path']) || !is_string($_POST['path']))
        {
            throw new Exception('A valid site path is required.');
        }

        if (!isset($_POST['root_dir']) ||
            !is_string($_POST['root_dir']) ||
            !in_array($_POST['root_dir'], array('0', '1'), true))
        {
            throw new Exception('Invalid staging base directory.');
        }

        $name = wp_unslash($_POST['path']);
        $base = $_POST['root_dir'] === '0' ? ABSPATH : WP_CONTENT_DIR;

        return array(
            'name' => $name,
            'path' => WPvivid_Setting::validate_child_directory($base, $name)
        );
    }
    /** @throws \Exception */
    public function get_staging_task($task_id)
    {
        if (!is_string($task_id) || strlen($task_id) > 64 ||
            preg_match('/\Awpvivid-[a-f0-9]+\z/', $task_id) !== 1)
        {
            throw new Exception('Invalid staging task ID.');
        }
        $tasks = get_option('wpvivid_staging_task_list', array());
        if (!is_array($tasks) || !isset($tasks[$task_id]) ||
            !is_array($tasks[$task_id]) || !isset($tasks[$task_id]['id']) ||
            $tasks[$task_id]['id'] !== $task_id)
        {
            throw new Exception('Staging task not found.');
        }
        return new WPvivid_Staging_Task($task_id);
    }

    private function normalize_staging_path($path)
    {
        $path = untrailingslashit(wp_normalize_path($path));
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    /** @throws \Exception */
    private function validate_staging_target_path($path)
    {
        if (!is_string($path) || $path === '' ||
            preg_match('/[\x00-\x1F\x7F]/', $path))
        {
            throw new Exception('Invalid staging path.');
        }
        $normalized = str_replace('\\', '/', $path);
        if (preg_match('#(?:\A|/)\.{1,2}(?:/|\z)#', $normalized) || is_link($path))
        {
            throw new Exception('Invalid staging path.');
        }
        $parent = realpath(dirname($path));
        if ($parent === false)
        {
            throw new Exception('The staging parent directory does not exist.');
        }
        $allowed = false;
        foreach (array(ABSPATH, WP_CONTENT_DIR) as $base)
        {
            $resolved = realpath($base);
            if ($resolved !== false &&
                $this->normalize_staging_path($parent) === $this->normalize_staging_path($resolved))
            {
                $allowed = true;
            }
        }
        if (!$allowed)
        {
            throw new Exception('The staging path is outside the allowed location.');
        }
        $target = $parent . DIRECTORY_SEPARATOR . basename($path);
        if (file_exists($target))
        {
            $resolved = realpath($target);
            if ($resolved === false || !is_dir($resolved) ||
                $this->normalize_staging_path(dirname($resolved)) !== $this->normalize_staging_path($parent))
            {
                throw new Exception('Invalid staging target directory.');
            }
            $target = $resolved;
        }
        $protected = array(ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR, get_theme_root(),
            ABSPATH . 'wp-admin', ABSPATH . 'wp-includes');
        $uploads = wp_get_upload_dir();
        if (!empty($uploads['basedir'])) $protected[] = $uploads['basedir'];
        $candidate = $this->normalize_staging_path($target);
        foreach ($protected as $directory)
        {
            $resolved = realpath($directory);
            $protected_path = $this->normalize_staging_path($resolved !== false ? $resolved : $directory);
            if ($candidate === $protected_path || strpos($protected_path . '/', $candidate . '/') === 0)
            {
                throw new Exception('The staging path overlaps the live site.');
            }
        }
        return $target;
    }

    /** @throws \Exception */
    private function acquire_staging_lock($task_id)
    {
        $folder = $this->log->GetSaveLogFolder();
        if (!is_dir($folder) && !wp_mkdir_p($folder))
        {
            throw new Exception('Failed to create the staging lock directory.');
        }
        $path = trailingslashit($folder) . 'wpvivid-staging-' . $task_id . '.lock';
        if (is_link($path)) throw new Exception('Invalid staging lock file.');
        $handle = @fopen($path, 'c');
        if (!$handle) throw new Exception('Failed to open the staging lock file.');
        if (!flock($handle, LOCK_EX | LOCK_NB))
        {
            fclose($handle);
            return false;
        }
        return $handle;
    }

    private function release_staging_lock($handle)
    {
        if (is_resource($handle))
        {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        // Keep the lock inode: unlinking it can allow concurrent locks on different files.
    }

    /** @throws \Exception */
    private function validate_staging_prefix($prefix)
    {
        if (!is_string($prefix) || $prefix === '' || strlen($prefix) > 64 ||
            preg_match('/\A[a-zA-Z0-9_]+\z/', $prefix) !== 1)
        {
            throw new Exception('Invalid staging table prefix.');
        }
        return $prefix;
    }

    private function staging_connection_hash($connection)
    {
        return hash('sha256', serialize(array(
            !empty($connection['des_use_additional_db']),
            !empty($connection['des_use_additional_db']) ? (string) $connection['des_dbhost'] : '',
            !empty($connection['des_use_additional_db']) ? (string) $connection['des_dbname'] : '',
            !empty($connection['des_use_additional_db']) ? (string) $connection['des_dbuser'] : '',
            !empty($connection['des_use_additional_db']) ? (string) $connection['des_dbpassword'] : ''
        )));
    }

    /** @throws \Exception */
    private function staging_database($connection)
    {
        if (!is_array($connection)) throw new Exception('Invalid staging database information.');
        if (!empty($connection['des_use_additional_db']))
        {
            foreach (array('des_dbhost', 'des_dbname', 'des_dbuser', 'des_dbpassword') as $key)
            {
                if (!isset($connection[$key]) || !is_string($connection[$key]))
                    throw new Exception('Invalid staging database information.');
            }
        }
        return $this->wpvivid_get_staging_database_object(
            !empty($connection['des_use_additional_db']),
            isset($connection['des_dbuser']) ? $connection['des_dbuser'] : '',
            isset($connection['des_dbpassword']) ? $connection['des_dbpassword'] : '',
            isset($connection['des_dbname']) ? $connection['des_dbname'] : '',
            isset($connection['des_dbhost']) ? $connection['des_dbhost'] : ''
        );
    }

    /** @throws \Exception */
    private function staging_database_tables($db)
    {
        $tables = $db->get_col('SHOW TABLES');
        if (!is_array($tables) || !empty($db->last_error))
            throw new Exception('Failed to inspect the staging database tables.');
        return array_values($tables);
    }

    /** @throws \Exception */
    private function validate_staging_task_scope($task)
    {
        if ($task->is_restore() || $task->is_copy())
            throw new Exception('This endpoint only executes newly created staging tasks.');
        $data = $task->task;
        if (empty($data['cleanup']) || !is_array($data['cleanup']) ||
            !isset($data['path']['des_path'], $data['db_connect']))
            throw new Exception('The task has no verified staging cleanup information. Please recreate it.');
        $scope = $data['cleanup'];
        $path = $this->validate_staging_target_path($data['path']['des_path']);
        $prefix = $this->validate_staging_prefix($data['db_connect']['new_prefix']);
        if ($this->normalize_staging_path($path) !== $scope['path'] ||
            $prefix !== $scope['prefix'] ||
            $this->staging_connection_hash($data['db_connect']) !== $scope['connection_hash'])
            throw new Exception('The staging task destination has changed.');
        return $scope;
    }

    /**
     * Authorize an exact table before the staging copy/installation may write it.
     * @throws \Exception
     */
    public function authorize_staging_table($task_id, $table_name)
    {
        $task = $this->get_staging_task($task_id);
        // Existing restore/copy jobs use their own workflow, not the new-create cleanup manifest.
        if ($task->is_restore() || $task->is_copy()) return;
        $scope = $this->validate_staging_task_scope($task);
        if (!is_string($table_name) || strpos($table_name, $scope['prefix']) !== 0 ||
            in_array($table_name, $scope['preexisting_tables'], true))
            throw new Exception('The destination table is not owned by this staging task.');
        $db = $this->staging_database($task->task['db_connect']);
        $tables = $this->staging_database_tables($db);
        if (in_array($table_name, $tables, true) && !in_array($table_name, $scope['tables'], true))
            throw new Exception('The destination table already exists outside this staging task.');
        if (!in_array($table_name, $scope['tables'], true))
        {
            $task->task['cleanup']['tables'][] = $table_name;
            $task->update_task();
        }
    }

    /** @throws \Exception */
    private function capture_staging_tables($task_id)
    {
        $task = $this->get_staging_task($task_id);
        $scope = $this->validate_staging_task_scope($task);
        $db = $this->staging_database($task->task['db_connect']);
        foreach ($this->staging_database_tables($db) as $name)
        {
            if (strpos($name, $scope['prefix']) === 0 &&
                !in_array($name, $scope['preexisting_tables'], true) &&
                !in_array($name, $task->task['cleanup']['tables'], true))
                $task->task['cleanup']['tables'][] = $name;
        }
        $task->update_task();
    }

    /**
     * Return false while the cancelled task still has an active worker.
     * @throws \Exception
     */
    public function acknowledge_staging_cancel($task_id)
    {
        $task = $this->get_staging_task($task_id);
        $lock = $this->acquire_staging_lock($task_id);
        if ($lock === false) return false;
        try
        {
            $task->get_task();
            if ($task->is_restore() || $task->is_copy())
                throw new Exception('This task cannot be cleaned as a new staging site.');
            $task->task['status']['str'] = 'cancel';
            $task->task['status']['error'] = 'Task cancelled.';
            $task->update_task();
            update_option('wpvivid_staging_task_cancel', false, 'no');
            return true;
        }
        finally
        {
            $this->release_staging_lock($lock);
        }
    }

    /** @throws \Exception */
    public function prepare_staging_task_resume($task_id)
    {
        $task = $this->get_staging_task($task_id);
        $this->validate_staging_task_scope($task);
        $current = get_option('wpvivid_current_running_staging_task', '');
        if ($current !== '' && $current !== $task_id)
            throw new Exception('Another staging task is active.');
        if ($task->get_status() === 'cancel' || $task->task['status']['str'] === 'completed')
            throw new Exception('This staging task cannot be resumed.');
        $lock = $this->acquire_staging_lock($task_id);
        if ($lock !== false)
        {
            try
            {
                $task->get_task();
                if ($task->task['status']['str'] === 'error')
                {
                    $task->task['status']['str'] = 'ready';
                    $task->task['status']['error'] = '';
                    $task->update_task();
                }
            }
            finally
            {
                $this->release_staging_lock($lock);
            }
        }
        update_option('wpvivid_current_running_staging_task', $task_id, 'no');
        return array('result' => 'success', 'task_id' => $task_id);
    }

}