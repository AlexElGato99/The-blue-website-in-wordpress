<?php

if (!defined('WPVIVID_PLUGIN_DIR')){
    die;
}

class WPvivid_tools
{
    public static function clean_junk_cache(){
        $home_url_prefix=get_home_url();
        $parse = wp_parse_url($home_url_prefix);
        $tmppath='';
        if(isset($parse['path'])) {
            $tmppath=str_replace('/','_',$parse['path']);
        }
        $home_url_prefix = $parse['host'].$tmppath;
        $path = WP_CONTENT_DIR.DIRECTORY_SEPARATOR.WPvivid_Setting::get_backupdir();
        $handler=opendir($path);
        if($handler===false)
        {
            return ;
        }
        while(($filename=readdir($handler))!==false)
        {
            /*if(is_dir($path.DIRECTORY_SEPARATOR.$filename) && preg_match('#temp-'.$home_url_prefix.'_'.'#',$filename))
            {
                WPvivid_tools::deldir($path.DIRECTORY_SEPARATOR.$filename,'',true);
            }
            if(is_dir($path.DIRECTORY_SEPARATOR.$filename) && preg_match('#temp-'.'#',$filename))
            {
                WPvivid_tools::deldir($path.DIRECTORY_SEPARATOR.$filename,'',true);
            }*/
            if(preg_match('#pclzip-.*\.tmp#', $filename)){
                @wp_delete_file($path.DIRECTORY_SEPARATOR.$filename);
            }
            if(preg_match('#pclzip-.*\.gz#', $filename)){
                @wp_delete_file($path.DIRECTORY_SEPARATOR.$filename);
            }
        }
        @closedir($handler);
    }

    public static function deldir($path,$exclude='',$flag = false)
    {
        if(!is_dir($path))
        {
            return ;
        }
        $handler=opendir($path);
        if(empty($handler))
            return ;
        while(($filename=readdir($handler))!==false)
        {
            if($filename != "." && $filename != "..")
            {
                if(is_dir($path.DIRECTORY_SEPARATOR.$filename)){
                    if(empty($exclude)||WPvivid_tools::regex_match($exclude['directory'],$path.DIRECTORY_SEPARATOR.$filename ,0)){
                        self::deldir( $path.DIRECTORY_SEPARATOR.$filename ,$exclude, $flag);
                        @rmdir( $path.DIRECTORY_SEPARATOR.$filename );
                    }
                }else{
                    if(empty($exclude)||WPvivid_tools::regex_match($exclude['file'],$path.DIRECTORY_SEPARATOR.$filename ,0)){
                        @wp_delete_file($path.DIRECTORY_SEPARATOR.$filename);
                    }
                }
            }
        }
        if($handler)
            @closedir($handler);
        if($flag)
            @rmdir($path);
    }

    public static function regex_match($regex_array,$string,$mode)
    {
        if(empty($regex_array))
        {
            return true;
        }

        if($mode==0)
        {
            foreach ($regex_array as $regex)
            {
                if(preg_match($regex,$string))
                {
                    return false;
                }
            }

            return true;
        }

        if($mode==1)
        {
            foreach ($regex_array as $regex)
            {
                if(preg_match($regex,$string))
                {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    public static function GetSaveLogFolder()
    {
        require_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-setting.php';
        return WPvivid_Setting::get_log_directory();
    }
}