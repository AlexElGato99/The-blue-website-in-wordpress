<?php
if (!defined('WPVIVID_PLUGIN_DIR'))
{
    die;
}

/** Free Staging AJAX transport and request authorization. */
class WPvivid_Staging_Ajax_Free
{
    private $staging;

    public function __construct($staging)
    {
        $this->staging = $staging;
        $this->register_ajax();
    }

    private function register_ajax()
    {
        add_action('wp_ajax_wpvividstg_start_staging_free', array($this, 'start_staging'));
        add_action('wp_ajax_wpvividstg_do_task_free', array($this, 'do_staging_task'));
        add_action('wp_ajax_wpvividstg_set_restart_staging_id_free', array($this, 'set_restart_staging_id'));
        add_action('wp_ajax_wpvividstg_get_staging_progress_free', array($this, 'get_staging_progress'));
        add_action('wp_ajax_wpvividstg_delete_site_free', array($this, 'delete_site'));
        add_action('wp_ajax_wpvividstg_delete_cancel_staging_site_free', array($this, 'delete_cancel_staging_site'));
        add_action('wp_ajax_wpvividstg_check_staging_dir_free', array($this, 'check_staging_dir'));
        add_action('wp_ajax_wpvividstg_get_custom_database_tables_info_free', array($this, 'get_custom_database_tables_info'));
        add_action('wp_ajax_wpvividstg_cancel_staging_free', array($this, 'cancel_staging'));
        add_action('wp_ajax_wpvividstg_test_additional_database_connect_free', array($this, 'test_additional_database_connect'));
        add_action('wp_ajax_wpvividstg_update_staging_exclude_extension_free', array($this, 'update_staging_exclude_extension'));
        add_action('wp_ajax_wpvividstg_get_custom_database_size_free', array($this, 'get_custom_database_size'));
        add_action('wp_ajax_wpvividstg_get_custom_files_size_free', array($this, 'get_custom_files_size'));
    }

    private function check_staging_ajax_security()
    {
        check_ajax_referer('wpvivid_ajax', 'nonce');

        // This restriction applies only to the Free Staging endpoints.
        if (is_multisite())
        {
            $check = current_user_can('manage_network_options');
        }
        else
        {
            $check = current_user_can('manage_options');
        }

        $check = apply_filters('wpvivid_ajax_check_security', $check);
        if (!$check)
        {
            echo wp_json_encode(array(
                'result' => 'failed',
                'error' => 'Permission denied.'
            ));
            die();
        }
    }

    public function start_staging()
    {
        $this->check_staging_ajax_security();
        try { $ret = $this->staging->create_staging_task(); }
        catch (Exception $error) { $ret = array('result' => 'failed', 'error' => $error->getMessage()); }
        echo wp_json_encode($ret);
        die();
    }

    public function do_staging_task()
    {
        $this->check_staging_ajax_security();
        try
        {
            $ret = $this->staging->run_staging_task($this->get_requested_staging_task_id());
            // The task's existing flush() may already have sent the execution response.
            if ($ret !== false) echo wp_json_encode($ret);
        }
        catch (Exception $error)
        {
            echo wp_json_encode(array('result' => 'failed', 'error' => $error->getMessage()));
        }
        die();
    }

    /** @throws \Exception */
    private function get_requested_staging_task_id($key = 'task_id')
    {
        if (!isset($_POST[$key]) || !is_string($_POST[$key]))
            throw new Exception('A staging task ID is required.');
        $id = wp_unslash($_POST[$key]);
        $this->staging->get_staging_task($id);
        return $id;
    }

    public function set_restart_staging_id()
    {
        $this->check_staging_ajax_security();
        try { $ret = $this->staging->prepare_staging_task_resume($this->get_requested_staging_task_id('id')); }
        catch (Exception $error) { $ret = array('result' => 'failed', 'error' => $error->getMessage()); }
        echo wp_json_encode($ret);
        die();
    }

    public function get_staging_progress()
    {
        global $wpvivid_plugin;
        $this->check_staging_ajax_security();

        $task_id=get_option('wpvivid_current_running_staging_task','');
        if(empty($task_id))
        {
            $list = get_option('wpvivid_staging_task_list',array());
            if(!empty($list))
            {
                foreach ($list as $key => $value)
                {
                    if(in_array($value['status']['str'], array('running', 'ready', 'cancel', 'error'), true))
                    {
                        $task_id = $value['id'];
                        update_option('wpvivid_current_running_staging_task', $task_id, 'no');
                        break;
                    }
                }
            }
            if(empty($task_id))
            {
                $ret['result']='success';
                $ret['log']='';
                $ret['continue']=0;
                echo wp_json_encode($ret);
                die();
            }
        }

        try
        {
            $task=$this->staging->get_staging_task($task_id);
            $ret['task_id'] = $task_id;
            $b_delete=false;
            if($task->get_status()=='completed')
            {
                if($task->is_restore()){
                    $ret['completed_msg'] = 'Pushing the staging site to the live site completed successfully.';
                }
                else{
                    $ret['completed_msg'] = 'Updating the staging site completed successfully.';

                    $db_connect = $task->get_db_connect();
                    $url = $db_connect['new_site_url'];
                    $options=array();
                    $options['timeout']=30;
                    $response = wp_remote_request( $url,$options);
                    if(!is_wp_error($response) && ($response['response']['code'] == 200))
                    {
                        $this->staging->log->OpenLogFile($task->get_log_file_name());
                        $this->staging->log->WriteLog('Access staging site successfully.', 'notice');
                    }
                    else
                    {
                        $this->staging->log->OpenLogFile($task->get_log_file_name());
                        $this->staging->log->WriteLog('Access staging site failed.', 'notice');
                    }
                }
                update_option('wpvivid_current_running_staging_task','','no');
                $ret['continue']=0;
                $ret['completed']=1;
            }
            else if($task->get_status()=='ready')
            {
                $ret['continue']=1;
                $ret['need_restart']=1;
            }
            else if($task->get_status()=='error')
            {
                $ret['continue']=0;
                $ret['error']=1;
                $ret['error_msg']=$task->get_error();
                $b_delete=true;
            }
            else if($task->get_status()=='cancel')
            {
                if ($this->staging->acknowledge_staging_cancel($task_id))
                {
                    $ret['continue'] = 0;
                    $ret['need_restart'] = 0;
                    $ret['is_cancel'] = 1;
                }
                else
                {
                    // Wait for the worker lock to be released before offering cleanup.
                    $ret['continue'] = 1;
                    $ret['need_restart'] = 0;
                }
            }
            else
            {
                if($task->check_timeout())
                {
                    if($task->get_status()=='ready')
                    {
                        $ret['continue']=1;
                        $ret['need_restart']=1;
                    }
                    else
                    {
                        update_option('wpvivid_current_running_staging_task','','no');
                        $ret['continue']=0;
                        $b_delete=true;
                    }
                }
                else
                {
                    $ret['continue']=1;
                    $ret['need_restart']=0;
                }
            }
            $staging_percent = $task->get_progress();
            $file_name=$this->staging->log->GetSaveLogFolder(). $task->get_log_file_name().'_log.txt';
            $file =fopen($file_name,'r');
            $buffer='';
            if(!$file)
            {
                $buffer='open log file failed';
            }
            else
            {
                if(filesize($file_name)<=1*1024*1024)
                {
                    while(!feof($file))
                    {
                        $buffer .= fread($file,1024);
                    }
                }
                else
                {
                    $pos=-2;
                    $eof='';
                    $n=50;
                    $buffer_array = array();
                    while($n>0)
                    {
                        while($eof!=="\n")
                        {
                            if(!fseek($file, $pos, SEEK_END))
                            {
                                $eof=fgetc($file);
                                $pos--;
                            }
                            else
                            {
                                break;
                            }
                        }
                        $buffer_array[].=fgets($file);
                        $eof='';
                        $n--;
                    }

                    if(!empty($buffer_array))
                    {
                        $buffer_array = array_reverse($buffer_array);
                        foreach($buffer_array as $value)
                        {
                            $buffer.=$value;
                        }
                    }
                }

                fclose($file);
            }

            if($b_delete)
            {
                $cleanup_ret = $this->staging->_delete_site($task_id, true);
                if ($cleanup_ret['result'] !== 'success')
                {
                    $ret['error'] = 1;
                    $ret['error_msg'] = $cleanup_ret['error'];
                }
            }
            $ret['log']=$buffer;
            $ret['percent']=$staging_percent;
            $ret['result']='success';
            echo wp_json_encode($ret);
        }
        catch (Exception $error)
        {
            $ret['result']='failed';
            $ret['error']=$error->getMessage();
            echo wp_json_encode($ret);
        }

        die();
    }

    public function delete_site()
    {
        global $wpvivid_plugin;
        $this->check_staging_ajax_security();
        try {
            if (isset($_POST['id'])) {
                $id = $this->get_requested_staging_task_id('id');
            } else {
                die();
            }

            $ret = $this->staging->_delete_site($id);

            $html = '';
            $list = get_option('wpvivid_staging_task_list', array());
            if (!empty($list)) {
                $display_list = new WPvivid_Staging_List();
                $display_list->set_parent('wpvivid_staging_list');
                $display_list->set_list($list);
                $display_list->prepare_items();
                ob_start();
                $display_list->display();
                $html = ob_get_clean();
            }
            $ret['html'] = $html;
            echo wp_json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function delete_cancel_staging_site()
    {
        $this->check_staging_ajax_security();
        try
        {
            $ret = $this->staging->_delete_site($this->get_requested_staging_task_id(), true, true);
        }
        catch (Exception $error) { $ret = array('result' => 'failed', 'error' => $error->getMessage()); }
        echo wp_json_encode($ret);
        die();
    }

    public function check_staging_dir()
    {
        global $wpvivid_plugin;
        $this->check_staging_ajax_security();
        try
        {
            $ret['result'] = 'success';
            if(!isset($_POST['path']) || empty($_POST['path']) || !is_string($_POST['path']))
            {
                $ret['result']='failed';
                $ret['error']='A site path is required.';
                echo wp_json_encode($ret);
                die();
            }

            $validated_directory = $this->staging->get_validated_staging_directory();
            $path = $validated_directory['path'];

            if(!isset($_POST['table_prefix']) || empty($_POST['table_prefix']) || !is_string($_POST['table_prefix']))
            {
                $ret['result']='failed';
                $ret['error']='A table prefix is required.';
                echo wp_json_encode($ret);
                die();
            }

            $table_prefix = sanitize_text_field($_POST['table_prefix']);

            if (file_exists($path))
            {
                $ret['result'] = 'failed';
                $ret['error'] = 'A folder with the same name already exists in website\'s root directory.';
            }
            else
            {
                if (mkdir($path, 0755, true))
                {
                    $test_file_name = 'wpvividstg_test_file.txt';
                    $test_file_path = $path.DIRECTORY_SEPARATOR.$test_file_name;
                    $mk_res = fopen($test_file_path, 'wb');
                    if (!$mk_res)
                    {
                        if(file_exists($path))
                            @rmdir($path);
                        $ret['result']='failed';
                        $ret['error']='The directory where the staging site will be installed is not writable. Please set the permissions of the directory to 755 then try it again.';
                        echo wp_json_encode($ret);
                        die();
                    }
                    fclose($mk_res);
                    @wp_delete_file($test_file_path);
                    @rmdir($path);
                } else {
                    $ret['result'] = 'failed';
                    $ret['error'] = 'Create directory is not allowed in ' . $path . '.Please check the directory permissions and try again';
                }
            }

            if(isset($_POST['additional_db']))
            {
                $additional_db_json = sanitize_text_field($_POST['additional_db']);
                $additional_db_json = stripslashes($additional_db_json);
                $additional_db_options = json_decode($additional_db_json, true);
                if($additional_db_options['additional_database_check'] === '1')
                {
                    $db_user = sanitize_text_field($additional_db_options['additional_database_info']['db_user']);
                    $db_pass = sanitize_text_field($additional_db_options['additional_database_info']['db_pass']);
                    $db_host = sanitize_text_field($additional_db_options['additional_database_info']['db_host']);
                    $db_name = sanitize_text_field($additional_db_options['additional_database_info']['db_name']);
                    $db = new wpdb($db_user, $db_pass, $db_name, $db_host);
                    $sql = $db->prepare("SHOW TABLES LIKE %s;", $db->esc_like($table_prefix) . '%');
                    $result = $db->get_results($sql, OBJECT_K);
                    if (!empty($result))
                    {
                        $ret['result'] = 'failed';
                        $ret['error'] = 'The table prefix already exists.';
                    }
                }
                else
                {
                    global $wpdb;
                    $sql = $wpdb->prepare("SHOW TABLES LIKE %s;", $wpdb->esc_like($table_prefix) . '%');
                    $result = $wpdb->get_results($sql, OBJECT_K);
                    if (!empty($result))
                    {
                        $ret['result'] = 'failed';
                        $ret['error'] = 'The table prefix already exists.';
                    }
                }
            }
            else
            {
                global $wpdb;
                $sql = $wpdb->prepare("SHOW TABLES LIKE %s;", $wpdb->esc_like($table_prefix) . '%');
                $result = $wpdb->get_results($sql, OBJECT_K);
                if (!empty($result))
                {
                    $ret['result'] = 'failed';
                    $ret['error'] = 'The table prefix already exists.';
                }
            }
            echo wp_json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function get_custom_database_tables_info()
    {
        global $wpvivid_plugin;
        $this->check_staging_ajax_security();
        try {
            global $wpdb;
            $db = array();
            $use_additional_db = false;
            $staging_site_id = sanitize_key($_POST['id']);
            if(empty($_POST['id']))
            {
                $get_site_mu_single=false;
            }
            else
            {
                $task = new WPvivid_Staging_Task($staging_site_id);
                $site_id=$task->get_site_mu_single_site_id();
                $get_site_mu_single=$task->get_site_mu_single();
            }


            if (isset($_POST['is_staging']) && !empty($_POST['is_staging']) && is_string($_POST['is_staging'])&&$_POST['is_staging'] == '1')
            {
                $base_prefix = $task->get_site_prefix();
            }
            else
            {
                $base_prefix=$wpdb->base_prefix;
            }

            if (isset($_POST['is_staging']) && !empty($_POST['is_staging']) && is_string($_POST['is_staging']))
            {
                if ($_POST['is_staging'] == '1')
                {
                    $is_staging_site = true;

                    $prefix = $task->get_site_prefix();

                    $db = $task->get_site_db_connect();
                    if ($db['use_additional_db'] !== false)
                    {
                        $use_additional_db = true;
                    } else {
                        $use_additional_db = false;
                    }
                } else {
                    $is_staging_site = false;
                    $prefix = $wpdb->get_blog_prefix(0);
                }
            } else {
                $is_staging_site = false;
                $prefix = $wpdb->get_blog_prefix(0);
            }

            $ret['result'] = 'success';
            $ret['html'] = '';
            if (empty($prefix)) {
                echo wp_json_encode($ret);
                die();
            }

            $base_table = '';
            $woo_table = '';
            $other_table = '';
            $default_table = array($prefix . 'commentmeta', $prefix . 'comments', $prefix . 'links', $prefix . 'options', $prefix . 'postmeta', $prefix . 'posts', $prefix . 'term_relationships',
                $prefix . 'term_taxonomy', $prefix . 'termmeta', $prefix . 'terms', $prefix . 'usermeta', $prefix . 'users');
            $woo_table_arr = array($prefix.'actionscheduler_actions', $prefix.'actionscheduler_claims', $prefix.'actionscheduler_groups', $prefix.'actionscheduler_logs', $prefix.'aelia_dismissed_messages',
                $prefix.'aelia_exchange_rates_history', $prefix.'automatewoo_abandoned_carts', $prefix.'automatewoo_customer_meta', $prefix.'automatewoo_customers', $prefix.'automatewoo_events',
                $prefix.'automatewoo_guest_meta', $prefix.'automatewoo_guests', $prefix.'automatewoo_log_meta', $prefix.'automatewoo_logs', $prefix.'automatewoo_queue', $prefix.'automatewoo_queue_meta',
                $prefix.'automatewoo_unsubscribes', $prefix.'wc_admin_note_actions', $prefix.'wc_admin_notes', $prefix.'wc_am_api_activation', $prefix.'wc_am_api_resource', $prefix.'wc_am_associated_api_key',
                $prefix.'wc_am_secure_hash', $prefix.'wc_category_lookup', $prefix.'wc_customer_lookup', $prefix.'wc_download_log', $prefix.'wc_order_coupon_lookup', $prefix.'wc_order_product_lookup',
                $prefix.'wc_order_stats', $prefix.'wc_order_tax_lookup', $prefix.'wc_product_meta_lookup', $prefix.'wc_reserved_stock', $prefix.'wc_tax_rate_classes', $prefix.'wc_webhooks',
                $prefix.'woocommerce_api_keys', $prefix.'woocommerce_attribute_taxonomies', $prefix.'woocommerce_downloadable_product_permissions', $prefix.'woocommerce_log', $prefix.'woocommerce_order_itemmeta',
                $prefix.'woocommerce_order_items', $prefix.'woocommerce_payment_tokenmeta', $prefix.'woocommerce_payment_tokens', $prefix.'woocommerce_sessions', $prefix.'woocommerce_shipping_zone_locations',
                $prefix.'woocommerce_shipping_zone_methods', $prefix.'woocommerce_shipping_zones', $prefix.'woocommerce_tax_rate_locations', $prefix.'woocommerce_tax_rates');

            if ($is_staging_site) {
                $staging_option = WPvivid_Staging_Free::wpvivid_get_push_staging_history();
                if (empty($staging_option)) {
                    $staging_option = array();
                }
                if ($use_additional_db) {
                    $handle = new wpdb($db['dbuser'], $db['dbpassword'], $db['dbname'], $db['dbhost']);
                    $tables = $handle->get_results('SHOW TABLE STATUS', ARRAY_A);
                } else {
                    $tables = $wpdb->get_results('SHOW TABLE STATUS', ARRAY_A);
                }
            } else {
                $staging_option = WPvivid_Staging_Free::wpvivid_get_staging_history();
                if (empty($staging_option)) {
                    $staging_option = array();
                }
                $tables = $wpdb->get_results('SHOW TABLE STATUS', ARRAY_A);
            }

            if (is_null($tables)) {
                $ret['result'] = 'failed';
                $ret['error'] = 'Failed to retrieve the table information for the database. Please try again.';
                echo wp_json_encode($ret);
                die();
            }

            $tables_info = array();
            $has_base_table = false;
            $has_woo_table = false;
            $has_other_table = false;
            $base_count = 0;
            $woo_count = 0;
            $other_count = 0;
            $base_table_all_check = true;
            $woo_table_all_check = true;
            $other_table_all_check = true;
            foreach ($tables as $row)
            {
                if (preg_match('/^(?!' . $base_prefix . ')/', $row["Name"]) == 1)
                {
                    continue;
                }

                if($get_site_mu_single)
                {
                    $site_id=$task->get_site_mu_single_site_id();

                    if(!is_main_site($site_id))
                    {
                        if ( 1 == preg_match('/^' . $prefix . '/', $row["Name"]) )
                        {
                        }
                        else if ( 1 == preg_match('/^' . $base_prefix . '\d+_/', $row["Name"]) )
                        {
                            continue;
                        }
                        else
                        {
                            if($row["Name"]==$base_prefix.'users'||$row["Name"]==$base_prefix.'usermeta')
                            {

                            }
                            else
                            {
                                continue;
                            }
                        }
                    }
                    else
                    {
                        if ( 1 == preg_match('/^' . $base_prefix . '\d+_/', $row["Name"]) )
                        {
                            continue;
                        }
                        else
                        {
                            if($row["Name"]==$base_prefix.'blogs')
                                continue;
                            if($row["Name"]==$base_prefix.'blogmeta')
                                continue;
                            if($row["Name"]==$base_prefix.'sitemeta')
                                continue;
                            if($row["Name"]==$base_prefix.'site')
                                continue;
                        }
                    }
                }


                $tables_info[$row["Name"]]["Rows"] = $row["Rows"];
                $tables_info[$row["Name"]]["Data_length"] = size_format($row["Data_length"] + $row["Index_length"], 2);

                $checked = 'checked';
                if (!empty($staging_option['database_list'])) {
                    if ($is_staging_site) {
                        $tmp_row = $row["Name"];

                        $tmp_row = str_replace($base_prefix, $wpdb->base_prefix, $tmp_row);
                        if (in_array($tmp_row, $staging_option['database_list'])) {
                            $checked = '';
                        }
                    }
                    else if (in_array($row["Name"], $staging_option['database_list'])) {
                        $checked = '';
                    }
                }

                if (in_array($row["Name"], $default_table)) {
                    if ($checked == '') {
                        $base_table_all_check = false;
                    }
                    $has_base_table = true;

                    $base_table .= '<div class="wpvivid-text-line">
                                        <input type="checkbox" option="base_db" name="Database" value="'.esc_html($row["Name"]).'" '.esc_html($checked).' />
                                        <span class="wpvivid-text-line">'.esc_html($row["Name"]).'|Rows:'.$row["Rows"].'|Size:'.$tables_info[$row["Name"]]["Data_length"].'</span>
                                    </div>';
                    $base_count++;
                } else if(in_array($row['Name'], $woo_table_arr)){
                    if ($checked == '') {
                        $woo_table_all_check = false;
                    }
                    $has_woo_table = true;

                    $woo_table .= '<div class="wpvivid-text-line">
                                        <input type="checkbox" option="woo_db" name="Database" value="'.esc_html($row["Name"]).'" '.esc_html($checked).' />
                                        <span class="wpvivid-text-line">'.esc_html($row["Name"]).'|Rows:'.$row["Rows"].'|Size:'.$tables_info[$row["Name"]]["Data_length"].'</span>
                                   </div>';
                    $woo_count++;
                }
                else {
                    if ($checked == '') {
                        $other_table_all_check = false;
                    }
                    $has_other_table = true;

                    $other_table .= '<div class="wpvivid-text-line">
                                        <input type="checkbox" option="other_db" name="Database" value="'.esc_html($row["Name"]).'" '.esc_html($checked).' />
                                        <span class="wpvivid-text-line">'.esc_html($row["Name"]).'|Rows:'.$row["Rows"].'|Size:'.$tables_info[$row["Name"]]["Data_length"].'</span>
                                     </div>';
                    $other_count++;
                }
            }

            $ret['html'] = '<div style="padding-left:4em;margin-top:1em;">
                                        <div style="border-bottom:1px solid #eee;"></div>
                                     </div>';

            $base_table_html = '';
            $woo_table_html = '';
            $other_table_html = '';
            if ($has_base_table) {
                $base_all_check = '';
                if ($base_table_all_check) {
                    $base_all_check = 'checked';
                }
                $base_table_html .= '<div style="width:30%;float:left;box-sizing:border-box;padding-right:0.5em;padding-left:4em;">
                                        <div>
                                            <p>
                                                <span class="dashicons dashicons-list-view wpvivid-dashicons-blue"></span>
                                                <span><input type="checkbox" class="wpvivid-database-table-check wpvivid-database-base-table-check" '.esc_attr($base_all_check).'></span>
                                                <span><strong>Wordpress Default Tables</strong></span>
                                            </p>
                                        </div>
                                        <div style="height:250px;border:1px solid #eee;padding:0.2em 0.5em;overflow:auto;">
                                            '.$base_table.'
                                        </div>
                                        <div style="clear:both;"></div>
                                    </div>';
            }

            if ($has_other_table) {
                $other_all_check = '';
                if ($other_table_all_check) {
                    $other_all_check = 'checked';
                }

                if($has_woo_table){
                    $other_table_width = '40%';
                }
                else{
                    $other_table_width = '70%';
                }

                $other_table_html .= '<div style="width:'.$other_table_width.'; float:left;box-sizing:border-box;padding-left:0.5em;">
                                        <div>
                                            <p>
                                                <span class="dashicons dashicons-list-view wpvivid-dashicons-green"></span>
                                                <span><input type="checkbox" class="wpvivid-database-table-check wpvivid-database-other-table-check" '.esc_attr($other_all_check).'></span>
                                                <span><strong>Other Tables</strong></span>
                                            </p>
                                        </div>
                                        <div style="height:250px;border:1px solid #eee;padding:0.2em 0.5em;overflow-y:auto;">
                                            '.$other_table.'
                                        </div>
                                     </div>';
            }

            if($has_woo_table) {
                $woo_all_check = '';
                if ($woo_table_all_check) {
                    $woo_all_check = 'checked';
                }
                $woo_table_html .= '<div style="width:30%; float:left;box-sizing:border-box;padding-left:0.5em;">
                                        <div>
										    <p><span class="dashicons dashicons-list-view wpvivid-dashicons-orange"></span>
												<span><input type="checkbox" class="wpvivid-database-table-check wpvivid-database-woo-table-check" '.esc_attr($woo_all_check).'></span>
												<span><strong>WooCommerce Tables</strong></span>
											</p>
										</div>
										<div style="height:250px;border:1px solid #eee;padding:0.2em 0.5em;overflow:auto;">
											'.$woo_table.'
                                        </div>
                                    </div>';
            }

            $ret['html'] .= $base_table_html . $other_table_html . $woo_table_html;
            $ret['tables_info'] = $tables_info;
            echo wp_json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function cancel_staging()
    {
        $this->check_staging_ajax_security();
        try
        {
            $task_id = isset($_POST['task_id']) ? $this->get_requested_staging_task_id()
                : get_option('wpvivid_current_running_staging_task', '');
            if ($task_id === '')
            {
                $ret = array('result' => 'success');
            }
            else
            {
                $task = $this->staging->get_staging_task($task_id);
                if (get_option('wpvivid_current_running_staging_task', '') !== $task_id)
                    throw new Exception('This is not the current staging task.');
                $task->cancel_staging();
                $ret = array('result' => 'success', 'task_id' => $task_id);
            }
        }
        catch (Exception $error) { $ret = array('result' => 'failed', 'error' => $error->getMessage()); }
        echo wp_json_encode($ret);
        die();
    }

    public function test_additional_database_connect(){
        global $wpvivid_plugin;
        $this->check_staging_ajax_security();
        try {
            if (isset($_POST['database_info']) && !empty($_POST['database_info']) && is_string($_POST['database_info'])) {
                $data = sanitize_text_field($_POST['database_info']);
                $data = stripslashes($data);
                $json = json_decode($data, true);
                $db_user = sanitize_text_field($json['db_user']);
                $db_pass = sanitize_text_field($json['db_pass']);
                $db_host = sanitize_text_field($json['db_host']);
                $db_name = sanitize_text_field($json['db_name']);

                $db = new wpdb($db_user, $db_pass, $db_name, $db_host);
                // Can not connect to mysql
                if (!empty($db->error->errors['db_connect_fail']['0'])) {
                    $ret['result'] = 'failed';
                    $ret['error'] = 'Failed to connect to MySQL server. Please try again later.';
                    echo wp_json_encode($ret);
                    die();
                }

                // Can not connect to database
                $db->select($db_name);
                if (!$db->ready) {
                    $ret['result'] = 'failed';
                    $ret['error'] = 'Unable to connect to MySQL database. Please try again later.';
                    echo wp_json_encode($ret);
                    die();
                }
                $ret['result'] = 'success';

                echo wp_json_encode($ret);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function update_staging_exclude_extension(){
        global $wpvivid_plugin;
        $this->check_staging_ajax_security();
        try {
            if (isset($_POST['type']) && !empty($_POST['type']) && is_string($_POST['type']) &&
                isset($_POST['exclude_content']) && !empty($_POST['exclude_content']) && is_string($_POST['exclude_content'])) {
                $type = sanitize_text_field($_POST['type']);
                $value = sanitize_text_field($_POST['exclude_content']);

                $staging_option = WPvivid_Staging_Free::wpvivid_get_staging_history();
                if (empty($staging_option)) {
                    $staging_option = array();
                }

                if ($type === 'upload') {
                    $staging_option['upload_extension'] = array();
                    $str_tmp = explode(',', $value);
                    for ($index = 0; $index < count($str_tmp); $index++) {
                        if (!empty($str_tmp[$index])) {
                            $staging_option['upload_extension'][] = $str_tmp[$index];
                        }
                    }
                } else if ($type === 'content') {
                    $staging_option['content_extension'] = array();
                    $str_tmp = explode(',', $value);
                    for ($index = 0; $index < count($str_tmp); $index++) {
                        if (!empty($str_tmp[$index])) {
                            $staging_option['content_extension'][] = $str_tmp[$index];
                        }
                    }
                } else if ($type === 'additional_file') {
                    $staging_option['additional_file_extension'] = array();
                    $str_tmp = explode(',', $value);
                    for ($index = 0; $index < count($str_tmp); $index++) {
                        if (!empty($str_tmp[$index])) {
                            $staging_option['additional_file_extension'][] = $str_tmp[$index];
                        }
                    }
                }

                WPvivid_Staging_Free::wpvivid_set_staging_history($staging_option);

                $ret['result'] = 'success';
                echo wp_json_encode($ret);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function get_custom_database_size(){
        global $wpvivid_plugin;
        $this->check_staging_ajax_security();
        try
        {
            $ret['result']='success';

            global $wpdb;
            $tables = $wpdb->get_results('SHOW TABLE STATUS', ARRAY_A);
            if (is_null($tables)) {
                $ret['result'] = 'failed';
                $ret['error'] = 'Failed to retrieve the table information for the database. Please try again.';
                return $ret;
            }

            $db_size = 0;

            $base_table_size = 0;
            foreach ($tables as $row) {
                $base_table_size += ($row["Data_length"] + $row["Index_length"]);
            }

            $db_size = size_format($base_table_size, 2);

            $ret['database_size'] = $db_size;
            echo wp_json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function get_custom_files_size(){
        global $wpvivid_plugin;
        $this->check_staging_ajax_security();
        try
        {
            $upload_dir = wp_upload_dir();
            $path = $upload_dir['basedir'];
            $path = str_replace('\\','/',$path);
            $uploads_path = $path.'/';

            $content_dir = WP_CONTENT_DIR;
            $path = str_replace('\\','/',$content_dir);
            $content_path = $path.'/';

            if(!function_exists('get_home_path'))
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            $home_path = str_replace('\\','/', get_home_path());

            $themes_path = str_replace('\\','/', get_theme_root());
            $themes_path = $themes_path.'/';

            $plugins_path = str_replace('\\','/', WP_PLUGIN_DIR);
            $plugins_path = $plugins_path.'/';

            $ret['result']='success';
            $core_size = WPvivid_Staging_Free::get_custom_path_size('core', $home_path);
            $themes_size = WPvivid_Staging_Free::get_custom_path_size('themes', $themes_path);
            $plugins_size = WPvivid_Staging_Free::get_custom_path_size('plugins', $plugins_path);
            $uploads_size = WPvivid_Staging_Free::get_custom_path_size('uploads', $uploads_path);
            $content_size = WPvivid_Staging_Free::get_custom_path_size('content', $content_path);
            $additional_size = WPvivid_Staging_Free::get_custom_path_size('additional', $home_path);
            $ret['core_size'] = size_format($core_size, 2);
            $ret['themes_size'] = size_format($themes_size, 2);
            $ret['plugins_size'] = size_format($plugins_size, 2);
            $ret['uploads_size'] = size_format($uploads_size, 2);
            $ret['content_size'] = size_format($content_size, 2);
            $ret['additional_size'] = size_format($additional_size, 2);
            $ret['total_file_size'] = size_format($core_size+$themes_size+$plugins_size+$uploads_size+$content_size+$additional_size, 2);
            echo wp_json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function check_filesystem_permissions()
    {
        global $wpvivid_plugin;
        $this->check_staging_ajax_security();
        try{
            if(!isset($_POST['path']) || empty($_POST['path']) || !is_string($_POST['path']))
            {
                $ret['result']='failed';
                $ret['error']='A site path is required.';
                echo wp_json_encode($ret);
                die();
            }

            $validated_directory = $this->staging->get_validated_staging_directory();

            $src_path = untrailingslashit(ABSPATH);
            $des_path = $validated_directory['path'];

            $mk_res = mkdir($des_path,0755,true);
            if (!$mk_res)
            {
                $ret['result']='failed';
                $ret['error']='The directory where the staging site will be installed is not writable. Please set the permissions of the directory to 755 then try it again.';
                echo wp_json_encode($ret);
                die();
            }

            $test_file_name = 'wpvividstg_test_file.txt';
            $test_file_path = $des_path.DIRECTORY_SEPARATOR.$test_file_name;
            $mk_res = fopen($test_file_path, 'wb');
            if (!$mk_res)
            {
                if(file_exists($des_path))
                    @rmdir($des_path);
                $ret['result']='failed';
                $ret['error']='The directory where the staging site will be installed is not writable. Please set the permissions of the directory to 755 then try it again.';
                echo wp_json_encode($ret);
                die();
            }

            fclose($mk_res);
            @wp_delete_file($test_file_path);
            if(file_exists($des_path))
                @rmdir($des_path);

            $ret['result'] = 'success';
            echo wp_json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }
}
