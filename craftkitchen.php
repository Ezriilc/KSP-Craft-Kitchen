<?php // Created by Erickson Swift, copyright 2014-2015.
class Craftkitchen{
    static
        $admin_dir = '_downloads/ships'
        ,$admin_user = 'Ezriilc'
        ,$game_root = '_KSP_GameData' // Only for populating DB, NOT production.
        ,$do_parse_game_files = false // localhost also required - only for populating DB, NOT production.
        ,$parts_db = './_sqlite/KSP-GameData.sqlite3'
        ,$crafts_db = '' // Empty for default site db.
        ,$parts_table_name = 'parts'
        ,$crafts_table_name = 'KSP_crafts'
        ,$parts_table
        ,$crafts_table
        ,$cfg_node
        ,$max_craft_size = 1048576
        ,$public_uploads = '_downloads/uploads'
        ,$message
        ,$ships_data
        ,$game_data
        ,$uploads
        ,$output_file
        ,$first_run = true
        ,$part_stats = array(
            'folder' => ''
            ,'title' => ''
            ,'vendor' => ''
            
            ,'launchClamp' => false
            ,'engine' => false
            ,'intake' => false
            ,'reactionWheel' => false
            ,'command' => false
            ,'decoupler' => false
            ,'dockingNode' => false
            
            ,'intakeArea' => 0
            ,'PitchTorque' => 0
            ,'YawTorque' => 0
            ,'RollTorque' => 0
            ,'CrewCapacity' => 0
            ,'mass' => 0
            ,'dryMass' => 0
            ,'wetMass' => 0
            ,'nullMass' => 0
            ,'maxThrust' => 0
            ,'launchThrust' => 0
            
            ,'file_text' => ''
        )
        ,$craft_stats = array(
            'ship' => ''
            ,'version' => ''
            ,'description' => ''
            ,'type' => ''
            
            ,'pathname' => ''
            ,'user' => ''
            ,'stages'=>0
            ,'parts'=>0
            ,'stockParts'=>0
            ,'addonParts'=>0
            ,'unknownParts'=>0
            ,'struts'=>0
            
            ,'launchClamps'=>0
            ,'engines'=>0
            ,'intakes'=>0
            ,'reactionWheels'=>0
            ,'commands' => 0
            ,'decouplers'=>0
            ,'dockingNodes'=>0
            
            ,'intakeArea'=>0
            ,'PitchTorque'=>0
            ,'YawTorque'=>0
            ,'RollTorque'=>0
            ,'CrewCapacity'=>0
            ,'mass'=>0
            ,'dryMass'=>0
            ,'wetMass'=>0
            ,'nullMass'=>0
            ,'maxThrust'=>0
            ,'launchThrust'=>0
            
            ,'maxTWR'=>0
            ,'launchTWR'=>0
            ,'launchStage'=>0
            
            ,'stock_list'=>array()
            ,'addon_list'=>array()
            ,'unknown_list'=>array()
        )
        // Known resources: masses in tonnes per litre:
        ,$resources = array(
            'SolidFuel'=>0.0075
            ,'LiquidFuel'=>0.005
            ,'Oxidizer'=>0.005
            ,'IntakeAir'=>0.005 // Really?
            ,'MonoPropellant'=>0.004
            ,'XenonGas'=>0.0001
            ,'ElectricCharge'=>0.0
        )
        // Engines that don't have full thrust at launch/ASL.
        ,$weak_engines = array(
            'JetEngine'=>135.0
            ,'turboFanEngine'=>110.0
            ,'RAPIER'=>90.0
        )
    ;
    
    function __construct(){
        if( static::$first_run ){ static::$first_run = false;
            
            include_once('class/Database.php');
            static::$parts_db = new Database(static::$parts_db);
            static::$crafts_db = new Database(static::$crafts_db);
            
            include_once('class/CfgNode.php');
            static::$cfg_node = new CfgNode;
            
            foreach( static::$resources as $key => $val ){
                static::$part_stats[$key] = 0;
                static::$craft_stats[$key] = 0;
            }
            
            static::$parts_table = array(
                static::$parts_table_name => array(
                    'schema' => "
folder TEXT NOT NULL PRIMARY KEY",
                )
            );
            foreach( static::$part_stats as $key => $val ){
                if( $key === 'folder' ){ continue; }
                $db_val = 'false';
                if( gettype($val) === 'integer' ){
                    $db_val = '0';
                }
                static::$parts_table[static::$parts_table_name]['schema'] .= "
,".$key." TEXT DEFAULT ".$db_val;
            }
            static::$crafts_table = array(
                static::$crafts_table_name => array(
                    'schema' => "
id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT
,pathname TEXT UNIQUE NOT NULL
,date INTEGER NOT NULL ON CONFLICT REPLACE DEFAULT (strftime('%s','now'))",
                )
            );
            foreach( static::$craft_stats as $key => $val ){
                if( $key === 'pathname' ){ continue; }
                $db_val = 'false';
                if( gettype($val) === 'integer' ){
                    $db_val = '0';
                }
                static::$crafts_table[static::$crafts_table_name]['schema'] .= "
,".$key." TEXT DEFAULT ".$db_val;
            }
            
            $db_out1 = static::$parts_db->check_add_tables(static::$parts_table);
            $db_err1 = static::$parts_db->get_error();
            $db_out2 = static::$crafts_db->check_add_tables(static::$crafts_table);
            $db_err2 = static::$crafts_db->get_error();
            if( $db_err1 OR $db_err2 ){
                die(get_called_class().': DB error: '.$db_err1.'/'.$db_err2.'<br/>DB out: '.$db_out1.'/'.$db_out2);
            }
            
            foreach($_FILES as $file_key => $upload){
                if(
                    ! empty($upload['tmp_name'])
                    AND is_string($upload['tmp_name'])
                    AND is_uploaded_file($upload['tmp_name'])
                ){
                    static::$uploads[$file_key] = $upload;
                }
            }
        }
        
        if(
            $_SERVER['HTTP_HOST'] === 'localhost'
            AND static::$do_parse_game_files
        ){
            static::parse_game_files(); // Only for populating game DB, NOT for production.
        }
    // END of __construct().
    }
    
    static public function get_craft_info($craft_file){
        if( ! preg_match('/\.craft$/i',$craft_file) ){ return; }
        $craft_info = static::load_craft_info($craft_file);
        if( empty($craft_info) ){
            ignore_user_abort('true');
            set_time_limit(ini_get('max_execution_time')+30);
            $microtime = microtime(true);
            echo '<pre>Parsing new craft: "'.$craft_file.'"...';
            flush();
            if( ! $craft_info = static::parse_craft_file($craft_file) ){
                echo ' ERROR: Unrecognizable file content.';
            }elseif( ! static::save_craft_info($craft_info) ){
                echo ' ERROR: Can\'t save file.';
            }elseif( ! $craft_info = static::load_craft_info($craft_file) ){
                echo ' ERROR: Saved file info cannot be re-read.';
            }
            echo ' ('.round(microtime(true)-$microtime,1).' seconds)'.PHP_EOL .'</pre>';
            flush();
        }
        return $craft_info;
    }
    
    static private function load_craft_info($pathname){
        $table = static::$crafts_table_name;
        $read_data = static::$crafts_db->read($table,'*',array('pathname'=>$pathname));
        $db_err = static::$crafts_db->get_error();
        if(
            ! empty($read_data[0])
            AND empty($db_err)
        ){
            
            foreach( array('jpg','jpeg','png','gif') as $ext ){
                $image_pathname = preg_replace('/\.craft/i','.'.$ext,$pathname);
                if(
                    is_readable($image_pathname)
                    AND ! is_dir($image_pathname)
                ){
                    $read_data[0]['image'] = $image_pathname;
                    break;
                }
            }
            
            if(
                empty($read_data[0]['user'])
                AND preg_match('#^'.preg_quote(static::$admin_dir,'#').'/#i',$pathname)
            ){
                $read_data[0]['user'] = static::$admin_user;
            }
            return $read_data[0];
        }
    }
    
    static private function save_craft_info($craft_info,$overwrite=false){
        if( empty($craft_info['pathname']) ){ return; }
        
        $table = static::$crafts_table_name;
        $pathname = $craft_info['pathname'];
//$pathname = SQLite3::escapeString($pathname);
        $read_data = static::$crafts_db->read($table,'*',array('pathname'=>$pathname));
        $db_err = static::$crafts_db->get_error();
        if( ! empty($read_data[0]) ){
            // Craft found.
            if( empty($overwrite) ){ return false; }
            $write_data = $read_data[0];
        }else{
            $write_data = array();
        }
        $write_data['pathname'] = $pathname;
        $write_data['date'] = filemtime($pathname);
        
        foreach( static::$craft_stats as $stat_key => $stat_val ){
            if( $stat_key === 'pathname' ){ continue; }
            if( is_array($craft_info[$stat_key]) ){
                $i=0;
                $stat_val_string = '';
                foreach( $craft_info[$stat_key] as $key => $val ){
                    if( is_array($val) ){
                        foreach( $val as $key2 => $val2 ){
                            if($i++){ $stat_val_string .= '|'; }
                            $stat_val_string .= $key.'/'.$key2.'='.$val2;
                        }
                    }else{
                        if($i++){ $stat_val_string .= '|'; }
                        $stat_val_string .= $key.'='.$val;
                    }
                }
            }else{
                $stat_val_string = $craft_info[$stat_key];
            }
            $write_data[$stat_key] = static::my_escape_sql($stat_val_string);
$write_data[$stat_key] = SQLite3::escapeString($write_data[$stat_key]);
        }
        $write_data = array(
            array_keys($write_data),
            array_values($write_data)
        );
        
        $row_count = static::$crafts_db->write($table,$write_data,true);
        $db_err = static::$crafts_db->get_error();
        if(
            ! empty($row_count)
            AND empty($db_err)
        ){
            return true;
        }
    }
    
    static private function de_space($inout){
        return preg_replace('# #','%20',$inout);
    }
    
    static private function my_escape_sql($inout,$decode=false){
        // Flatten the goofy umlaut/diaeresis in .craft files.
        $token = '&uml;';
        $token_patt = preg_quote($token,'/');
        $return = '';
        if($decode){
            $return = preg_replace('/('.$token_patt.')+/', "\r\n", $inout);
        }elseif( preg_match("/(*UTF8)(\x{A8})/", $inout) ){
            // This only works for UTF8 files.
            $return = preg_replace("/(*UTF8)\x{A8}/", $token, $inout);
        }else{
            // This only works for NON-UTF8 "ANSI" files.
            // Because in UTF8 files, this matches the entire string (like: .*).
            $return = preg_replace("/\x{A8}/", $token, $inout);
        }
        return $return;
    }
    
    static private function log_uploads(){
        $logged = false;
        if(
            count($_FILES)
            // AND DB is ready...
        ){
            $time = date('Y-m-d @ H:i:s', $_SERVER['REQUEST_TIME']);
            $log_entry = '';
            $log_entry .= static::$message.PHP_EOL;
            $log_entry .= 'Output file: '.static::$output_file[0].PHP_EOL;
            $log_entry .= 'Input files ($_FILES):'.PHP_EOL;
            foreach( $_FILES as $file ){
                $log_entry .= "\t\"".$file['name']."\" (\"".$file['tmp_name']."\")".PHP_EOL;
                if( ! is_uploaded_file($file['tmp_name']) ){
                    $log_entry .= '\t\t! (NOT) is_uploaded_file() - SECURITY BREACH!'.PHP_EOL;
                }
            }
            // Log to DB here.
            if( ! class_exists('Mailer') ){
                include_once('class/Mailer.php');
            }
            $mailer = new Mailer;
            $mailer->send_message($log_entry,get_called_class());
        }
    }
    
    static private function get_game_data(){
        $table = static::$parts_table_name;
        $read_data = static::$parts_db->read($table);
        $db_err = static::$parts_db->get_error();
        if(
            ! empty($read_data)
            AND empty($db_err)
        ){
            static::$game_data['parts'] = array();
            foreach( $read_data as $each ){
                $folder = $each['folder'];
                unset($each['folder']);
                static::$game_data['parts'][$folder] = $each;
            }
            return true;
        }else{
            die(get_called_class().': Can\'t read game data. Please tell the admin.');
        }
    }
    
    static private function walk_stats($array){
        $array_new = array();
        foreach($array as $key => $val){
            $key_new = preg_replace('/#\d+$/','',$key);
            if( is_array($val) ){
                $array_new[$key_new] = static::walk_stats($val);
            }else{
                if( is_numeric($val) ){
                    if( $val == intval($val) ){
                        settype($val,'integer');
                    }else{
                        settype($val,'float');
                    }
                }
                $array_new[$key_new] = $val;
            }
        }
        return $array_new;
    }
    
    static public function display_craft($craft_info){
        $vendor_part_qty_patt = '/([^\/]*)\/([^=]*)=(.*)/';
        $part_qty_patt = '/([^=]*)=(.*)/';
        foreach( $craft_info as $prop => $val ){
            if( is_array($val) ){ continue; }
            $vals = explode('|',$val);
            if(
                ! empty($val)
                AND preg_match('/_list$/i', $prop)
            ){
                $val = array();
                foreach( $vals as $val2 ){
                    $vendor = preg_filter($vendor_part_qty_patt,'$1',$val2);
                    if( ! empty($vendor) ){
                        $part = preg_filter($vendor_part_qty_patt,'$2',$val2);
                        $qty = preg_filter($vendor_part_qty_patt,'$3',$val2);
                        $vendor = htmlentities($vendor);
                        if( empty($val[$vendor]) ){ $val[$vendor] = array(); }
                        $val[$vendor][htmlentities($part)] = htmlentities($qty);
                    }else{
                        $part = preg_filter($part_qty_patt,'$1',$val2);
                        $qty = preg_filter($part_qty_patt,'$2',$val2);
                        $val[htmlentities($part)] = htmlentities($qty);
                    }
                }unset($val2);
            }else{
                if( preg_match('/(Thrust|Mass|TWR|Fuel|Oxidizer|intakeArea|ElectricCharge)$/i',$prop) ){
                    $val = round($val,2);
                }
                $val = htmlentities($val);
            }
            unset($craft_info[$prop]);
            $craft_info[htmlentities($prop)] = $val;
        }unset($prop,$val);
        
        
        $craft_info['description'] = preg_replace('/(&amp;uml;)+/i',"\r\n",$craft_info['description']);
        
        $return = '';
        
        $v_button = '';
        $v_stats = '';
        $v_image = '';
        $v_desc = '';
        $v_details = '';
        $v_parts = '';
        $v_addons = '';
        $v_unknowns = '';
        
        // Download button:
        $v_button .= '
<div class="button">
    <a class="menu" title="Download '.basename($craft_info['pathname']).'" href="'.
dirname($craft_info['pathname']).'/'.rawurlencode(basename($craft_info['pathname']))
.'">'.basename($craft_info['pathname']);
        if( ! strstr(
            preg_replace( '/\W+/i', '_', basename($craft_info['pathname']) )
            ,preg_replace( '/\W+/i', '_', $craft_info['ship'] )
        )){
            $v_button .= ' ("'.$craft_info['ship'].'")';
        }
        $v_button .= '</a>
</div>';
        
        // File stats:
        $v_stats .= '<small>';
        $v_stats .= 'Uploaded <strong>'.date('M j, Y, g:ia',$craft_info['date']).'</strong>';
        if( ! empty($craft_info['user']) ){
            $v_stats .= ', by <strong>'.$craft_info['user'].'</strong>';
        }
        $v_stats .= '<br/>';
        if( ! empty($craft_info['dl_info']['count']) ){
            $v_stats .= 'Downloaded: <strong>'.number_format($craft_info['dl_info']['count']).'</strong> time(s).<br/>';
        }
        $v_stats .= 'Type: <strong>'.$craft_info['type'].' ';
        if( $craft_info['type'] === 'VAB' ){ $v_stats .= ' (Rocket)'; }
        elseif( $craft_info['type'] === 'SPH' ){ $v_stats .= ' (Plane)'; }
        $v_stats .= '</strong>; KSP Version: <strong>'.$craft_info['version'].'</strong>';
        $v_stats .= '</small>';
        
        // Ship image:
        if( ! empty($craft_info['image']) ){
            $v_image .= '
<div class="image">
    <a class="extlink" title="Zoom image." href="'.static::de_space($craft_info['image']).'"><img width="240" alt="'.$craft_info['ship'].'" src="'.static::de_space($craft_info['image']).'"/></a>
</div>';
        }
        
        // Ship details:
        $cell_pre = '<div class="propval"><div class="prop cell">';
        $cell_mid = '</div><div class="val cell">';
        $cell_suff = '</div></div>';
        $v_details .= '
<div class="details">
    <div class="group">
        '.$cell_pre.'Crew Cap.'.$cell_mid.$craft_info['CrewCapacity'].$cell_suff.'
        '.$cell_pre.'<span title="Probe Controllers and Cockpits">Cmd Ctrls(?)</span>'.$cell_mid.$craft_info['commands'].$cell_suff.'
        '.$cell_pre.'Stages'.$cell_mid.$craft_info['stages'].$cell_suff.'
        '.$cell_pre.'Launch Stg.'.$cell_mid.$craft_info['launchStage'].$cell_suff.'
        '.$cell_pre.'Clamps'.$cell_mid.$craft_info['launchClamps'].$cell_suff.'
    </div>
    <div class="group mass">
        '.$cell_pre.'Parts'.$cell_mid.$craft_info['parts'].$cell_suff.'
        '.$cell_pre.'Struts'.$cell_mid.$craft_info['struts'].$cell_suff.'
        '.$cell_pre.'Total Mass'.$cell_mid.$craft_info['mass'].$cell_suff.'
        '.$cell_pre.'<span title="This only includes known parts.">Dry Mass(?)</span>'.$cell_mid.$craft_info['dryMass'].$cell_suff.'
        '.$cell_pre.'Wet Mass'.$cell_mid.$craft_info['wetMass'].$cell_suff.'
        '.$cell_pre.'<span title="Some parts have no mass, like struts.">Null Mass(?)</span>'.$cell_mid.$craft_info['nullMass'].$cell_suff.'
    </div>
    <div class="group thrust">
        '.$cell_pre.'Max Thrust'.$cell_mid.$craft_info['maxThrust'].$cell_suff.'
        '.$cell_pre.'Launch Thr.'.$cell_mid.$craft_info['launchThrust'].$cell_suff.'
        '.$cell_pre.'<span title="Thrust/Weight Ratio.">Max TWR(?)</span>'.$cell_mid.$craft_info['maxTWR'].$cell_suff.'
        '.$cell_pre.'Launch TWR'.$cell_mid.$craft_info['launchTWR'].$cell_suff.'
    </div>
    <div class="group">
        '.$cell_pre.'Intake Area'.$cell_mid.$craft_info['intakeArea'].$cell_suff.'
        '.$cell_pre.'Pitch Torque'.$cell_mid.$craft_info['PitchTorque'].$cell_suff.'
        '.$cell_pre.'Yaw Torque'.$cell_mid.$craft_info['YawTorque'].$cell_suff.'
        '.$cell_pre.'Roll Torque'.$cell_mid.$craft_info['RollTorque'].$cell_suff.'
    </div>
    <div class="group">
        '.$cell_pre.'Engines'.$cell_mid.$craft_info['engines'].$cell_suff.'
        '.$cell_pre.'Intakes'.$cell_mid.$craft_info['intakes'].$cell_suff.'
        '.$cell_pre.'React Wheels'.$cell_mid.$craft_info['reactionWheels'].$cell_suff.'
        '.$cell_pre.'Decouplers'.$cell_mid.$craft_info['decouplers'].$cell_suff.'
        '.$cell_pre.'Dock Nodes'.$cell_mid.$craft_info['dockingNodes'].$cell_suff.'
    </div>
    <div class="group resources">
        '.$cell_pre.'Solid Fuel'.$cell_mid.$craft_info['SolidFuel'].$cell_suff.'
        '.$cell_pre.'Liquid Fuel'.$cell_mid.$craft_info['LiquidFuel'].$cell_suff.'
        '.$cell_pre.'Oxidizer'.$cell_mid.$craft_info['Oxidizer'].$cell_suff.'
        '.$cell_pre.'Mono Propel.'.$cell_mid.$craft_info['MonoPropellant'].$cell_suff.'
        '.$cell_pre.'Xenon Gas'.$cell_mid.$craft_info['XenonGas'].$cell_suff.'
        '.$cell_pre.'Electric Chg'.$cell_mid.$craft_info['ElectricCharge'].$cell_suff.'
    </div>
</div>';
    
        // Ship description:
        if( ! empty($craft_info['description']) ){
            $v_desc .= '
<div class="description">'.$craft_info['description'].'</div>';
        }
        
        // Stock parts:
        if( ! empty($craft_info['stockParts']) ){
            $v_parts .= '
<div style="clear:both;"></div>
<div class="parts">
    <h4>Stock Parts ('.$craft_info['stockParts'].')</h4>
    <ul class="group">';
            foreach( $craft_info['stock_list'] as $vendor => $parts ){
                $v_parts .= '
        <li>'.$cell_pre.'<strong>'.$vendor.' ('.array_sum($parts).' total, '.count($parts).' unique)</strong>'.$cell_suff.'<span style="display:block;clear:both;"></span></li>';
                foreach( $parts as $part => $qty ){
                    $v_parts .= '
        <li>'.$cell_pre.$part.$cell_mid.$qty.$cell_suff.'<span style="display:block;clear:both;"></span></li>';
                }unset($part,$qty);
            }unset($vendor,$parts);
            $v_parts .= '
    </ul>
</div>';
        }
        
        // Addon parts:
        if( ! empty($craft_info['addonParts']) ){
            $v_addons .= '
<div style="clear:both;"></div>
<div class="addons">
    <h4>Known Add-Ons ('.$craft_info['addonParts'].')</h4>
    <ul class="group">';
            foreach( $craft_info['addon_list'] as $vendor => $parts ){
                $v_addons .= '
        <li>'.$cell_pre.'<strong>'.$vendor.' ('.array_sum($parts).' total, '.count($parts).' unique)</strong>'.$cell_suff.'<span style="display:block;clear:both;"></span></li>';
                foreach( $parts as $part => $qty ){
                    $v_addons .= '
        <li>'.$cell_pre.$part.$cell_mid.$qty.$cell_suff.'<span style="display:block;clear:both;"></span></li>';
                }unset($part,$qty);
            }unset($vendor,$parts);
            $v_addons .= '
    </ul>
</div>';
        }
        
        // Unknown parts:
        if( ! empty($craft_info['unknown_list']) ){
            $v_unknowns .= '
<div style="clear:both;"></div>
<div class="unknowns">
    <h4>Unknown Add-Ons ('.$craft_info['unknownParts'].')</h4>
    <ul class="group">';
            $v_unknowns .= '
        <li>'.$cell_pre.'<strong>('.count($craft_info['unknown_list']).' unique)</strong>'.$cell_suff.'<span style="display:block;clear:both;"></span></li>';
            foreach( $craft_info['unknown_list'] as $part => $qty ){
                $v_unknowns .= '
        <li>'.$cell_pre.$part.$cell_mid.$qty.$cell_suff.'<span style="display:block;clear:both;"></span></li>';
            }unset($part,$qty);
            $v_unknowns .= '
    </ul>
</div>';
        }
    
        $return .=
            $v_button.$v_stats.$v_desc.$v_image.$v_details.$v_parts.$v_addons.$v_unknowns;
        
        return $return;
    }
    
    static private function parse_craft_file($craft_file){
        if( ! static::valid_craft_text($craft_file) ){ return; }
        if( ! static::$game_data ){
            static::get_game_data();
        }
        $craft_data = static::$cfg_node->cfg_to_array( file_get_contents($craft_file) );
        $craft_stats = array('pathname'=>$craft_file);
        $craft_parts = array();
        $engines = array();
        $first_clamp_stage = 0;
        $first_engine_stage = 0;
        $first_decoupler_stage = 0;
        $part_id_patt = '^(.+)_(\d+)$';
        // Directly import one-liners.
        foreach($craft_data as $key => $val){
            if( preg_match('/PART/', $key) ){
                $craft_parts[] = $val;
            }else{
                $craft_stats[preg_replace('/#\d+$/','',$key)] = preg_replace('/(\s+){3,}/','$1$1',$val);
            }
        }
        // Add basic stats.
        $craft_stats = array_merge(static::$craft_stats,$craft_stats);
        
        // Walk craft parts to determine staging.
        foreach( $craft_parts as $craft_part_key => $craft_part ){
            foreach( $craft_part as $craft_part_key => $craft_part_val ){
                if( preg_match('/^MODULE#/',$craft_part_key) ){
                    $module = $craft_part_val['name#0'];
                    if( preg_match('/LaunchClamp/i',$module) ){
                        $first_clamp_stage = 0+ $craft_part['istg#0'];
                    }elseif( preg_match('/ModuleEngines/i',$module) ){
                        if( $craft_part['istg#0'] > $first_engine_stage ){
                            $first_engine_stage = 0+ $craft_part['istg#0'];
                        }
                    }
                }
            }
        }
        $craft_stats['stages'] = $first_engine_stage+1;
        if( $first_clamp_stage ){
            $craft_stats['launchStage'] = $first_clamp_stage;
        }else{
            $craft_stats['launchStage'] = $craft_stats['stages']-1;
        }
        
        
        
        // Walk craft parts to flesh out stats.
        foreach( $craft_parts as $craft_part_key => $craft_part ){
            $craft_part_name = preg_filter('/'.$part_id_patt.'/','$1',$craft_part['part#0']);
            $craft_part_name_flat = preg_replace('/\./','_',$craft_part_name);
// Part names can have periods in the .craft file (part = ), but are SOMETIMES (usually) found as underscores in part.cfg (name = ) - Hulk smash(flatten).

            $craft_stats['parts']++;
            if( preg_match('/strut/i',$craft_part['part#0']) ){
                $craft_stats['struts']++;
            }
            
            // Find the part in GameData.
            unset( $game_part );
            if(
                ! empty(static::$game_data['parts'])
                AND array_key_exists($craft_part_name_flat, static::$game_data['parts'])
            ){
                $game_part = static::$game_data['parts'][$craft_part_name_flat];
            }
            if( ! isset($game_part) ){
                $craft_stats['unknownParts']++;
                if( empty($craft_stats['unknown_list'][$craft_part_name]) ){
                    $craft_stats['unknown_list'][$craft_part_name] = 1;
                }else{
                    $craft_stats['unknown_list'][$craft_part_name]++;
                }
            }else{
                $game_part_title = $game_part['title'];
                if(
                    $game_part['vendor'] === 'Squad'
                    OR $game_part['vendor'] === 'NASAmission'
                ){
                    $craft_stats['stockParts']++;
                    if( empty($craft_stats['stock_list'][$game_part['vendor']][$game_part_title]) ){
                        $craft_stats['stock_list'][$game_part['vendor']][$game_part_title] = 1;
                    }else{
                        $craft_stats['stock_list'][$game_part['vendor']][$game_part_title]++;
                    }
                }else{
                    $craft_stats['addonParts']++;
                    if( empty($craft_stats['addon_list'][$game_part['vendor']][$game_part_title]) ){
                        $craft_stats['addon_list'][$game_part['vendor']][$game_part_title] = 1;
                    }else{
                        $craft_stats['addon_list'][$game_part['vendor']][$game_part_title]++;
                    }
                }
                
                // Resources
                if( version_compare($craft_stats['version'],'0.22','>=') ){
                    // New ships have their own resource amounts.
                    foreach( $craft_part as $craft_part_key => $craft_part_val ){
                        if( preg_match('/^RESOURCE#/',$craft_part_key) ){
                            $resource = $craft_part_val['name#0'];
                            $amount = $craft_part_val['amount#0'];
                            @$craft_stats[$resource] += $amount;
                            if( array_key_exists($resource,static::$resources) ){
                                $craft_stats['wetMass'] += ($amount * static::$resources[$resource]);
                            }
                        }
                    }
                }else{
                    foreach( static::$resources as $resource => $weight ){
                        $amount = $game_part[$resource];
                        $craft_stats[$resource] += $amount;
                        $craft_stats['wetMass'] += ($amount * $weight);
                    }
                }
                
                if( $game_part['launchClamp'] ){
                    $craft_stats['launchClamps']++;
                    $craft_stats['nullMass'] += $game_part['dryMass'];
                }else{
                    $craft_stats['dryMass'] += $game_part['dryMass'];
                }
                $craft_stats['nullMass'] += $game_part['nullMass'];
                $craft_stats['CrewCapacity'] += @$game_part['CrewCapacity'];
                $craft_stats['PitchTorque'] += $game_part['PitchTorque'];
                $craft_stats['YawTorque'] += $game_part['YawTorque'];
                $craft_stats['RollTorque'] += $game_part['RollTorque'];
                $craft_stats['intakeArea'] += $game_part['intakeArea'];
                if( $game_part['command'] ){
                    $craft_stats['commands']++;
                }
                if( $game_part['reactionWheel'] ){
                    $craft_stats['reactionWheels']++;
                }
                if( $game_part['intake'] ){
                    $craft_stats['intakes']++;
                }
                if( $game_part['decoupler'] ){
                    $craft_stats['decouplers']++;
                }
                if( $game_part['dockingNode'] ){
                    $craft_stats['dockingNodes']++;
                }
                if( $game_part['engine'] ){
                    $craft_stats['engines']++;
                }
                $craft_stats['maxThrust'] += $game_part['maxThrust'];
                if(
                    $craft_part['istg#0'] >= $craft_stats['launchStage']
                    AND $game_part['engine']
                ){
                    $craft_stats['launchThrust'] += $game_part['launchThrust'];
                }
            }
        } // Finished second walkthrough.
        
        $craft_stats['mass'] = $craft_stats['wetMass'] + $craft_stats['dryMass'];
        if( $craft_stats['maxThrust'] && $craft_stats['mass'] ){
            $craft_stats['maxTWR'] = ( $craft_stats['maxThrust'] / ($craft_stats['mass'] * 9.80665) );
        }
        if( $craft_stats['launchThrust'] && $craft_stats['mass'] ){
            $craft_stats['launchTWR'] = ( $craft_stats['launchThrust'] / ($craft_stats['mass']*9.80665) );
        }
//var_dump($craft_stats);die('DONE');
        return $craft_stats;
    }
    
    static private function parse_game_files(){
        $time = time();
        set_time_limit( 60 );
        echo '<pre>Rebuilding game data from part files...'.PHP_EOL;
        flush();
        $part_files = static::find_files('/.+\.cfg$/i',static::$game_root);
        $root_patt = preg_quote(static::$game_root,'/');
        $parts_count = 0;
        $game_parts = array();
        foreach( $part_files as $part_file ){
            echo $part_file.PHP_EOL;
            flush();
            $part_vendor = preg_filter('/^'.$root_patt.'\/([^\/]+)\/.*$/i', '$1', $part_file);
            $file_text = file_get_contents($part_file);
            $part_data = static::$cfg_node->cfg_to_array( $file_text );
            if( isset($part_data['PART#0']) ){
                foreach( $part_data as $part => $nodes ){
                    $each_part = $nodes;
                    $each_name = $nodes['name#0'];
                    $each_name_flat = preg_replace('/\./','_',$each_name);
// Part names can have periods in the .craft file (part = ), but are SOMETIMES (usually) found as underscores in part.cfg (name = ) - Hulk smash(flatten).
                    $game_parts[$each_name_flat] = array_merge(array('vendor#0'=>$part_vendor),$nodes);
                    $game_parts[$each_name_flat]['file_text'] = $file_text;
                    $parts_count++;
                }
            }
        }
        $time2 = time();
        echo 'Parsed '.$parts_count.' parts in '.($time2-$time).' seconds.'.PHP_EOL;
        flush();
        $game_data = array('PARTS'=>$game_parts);
        
        // Insert/Update parts to DB.
        $parts_data = $game_data['PARTS'];
        $parts = array();
        foreach( $parts_data as $part_name => $part_data ){
            $part_stats = static::$part_stats;
            $part_stats['folder'] = $part_name;
            $part_stats['file_text'] = $part_data['file_text'];
            if( ! empty($part_data['title#0']) ){
                $part_stats['title'] = $part_data['title#0'];
            }
            if( ! empty($part_data['vendor#0']) ){
                $part_stats['vendor'] = $part_data['vendor#0'];
            }
            if( ! empty($part_data['CrewCapacity#0']) ){
                $part_stats['CrewCapacity'] += $part_data['CrewCapacity#0'];
            }
            foreach( $part_data as $data_key => $data_val ){
                // Walk modules and resources.
                if( preg_match('/^MODULE#/',$data_key) ){
                    $module = $data_val['name#0'];
                    if( preg_match('/LaunchClamp/i',$module) ){
                        $part_stats['launchClamp'] = true;
                    }
                    if( preg_match('/ModuleEngines/i',$module) ){
                        $part_stats['engine'] = true;
                        if(
                            empty($data_val['engineID#0'])
                            OR(
                                ! empty($data_val['engineID#0'])
                                AND ! preg_match('/ClosedCycle/i',$data_val['engineID#0'])
                            )
                        ){
                            $part_stats['maxThrust'] += $data_val['maxThrust#0'];
                        }
                    }
                    if( preg_match('/ModuleReactionWheel/i',$module) ){
                        $part_stats['reactionWheel'] = true;
                        $part_stats['PitchTorque'] += $data_val['PitchTorque#0'];
                        $part_stats['YawTorque'] += $data_val['YawTorque#0'];
                        $part_stats['RollTorque'] += $data_val['RollTorque#0'];
                    }
                    if( preg_match('/ModuleResourceIntake/i',$module) ){
                        $part_stats['intake'] = true;
                        $part_stats['intakeArea'] += $data_val['area#0'];
                    }
                    if( preg_match('/ModuleCommand/i',$module) ){
                        $part_stats['command'] = true;
                    }
                    if( preg_match('/Decouple/i',$module) ){
                        $part_stats['decoupler'] = true;
                    }
                    if( preg_match('/ModuleDockingNode/i',$module) ){
                        $part_stats['dockingNode'] = true;
                    }
                }elseif( preg_match('/^RESOURCE#/',$data_key) ){
                    $resource = $data_val['name#0'];
                    $amount = $data_val['amount#0'];
                    $part_stats[$resource] = $amount;
                    if( array_key_exists($resource,static::$resources) ){
                        $part_stats['wetMass'] += ($amount*static::$resources[$resource]);
                    }
                }
            }
            if( ! empty($part_data['mass#0']) ){
                // Count dry masses.
                // Some parts have NO PHYSICS(!).
                // PhysicsSignificance may be: -1(?), 0, 1, or unset. 1 is OFF!! << !!
                if( 
                    (
                        ! empty($part_data['PhysicsSignificance#0'])
                        AND $part_data['PhysicsSignificance#0'] === '1'
                    )OR(
                        ! empty($part_data['module#0'])
                        AND preg_match('/FuelLine|Strut/i',$part_data['module#0'])
                    )
                ){
                    $part_stats['nullMass'] += $part_data['mass#0'];
                }elseif( ! preg_match('/LaunchClamp/i',$part_name) ){
                    $part_stats['dryMass'] += $part_data['mass#0'];
                }
                $part_stats['mass'] = $part_stats['wetMass'] + $part_stats['dryMass'];
            }
            
            // Correct launchThrust for weak ASL performance.
            if( array_key_exists($part_name,static::$weak_engines) ){
                $part_stats['launchThrust'] += static::$weak_engines[$part_name];
            }else{
                $part_stats['launchThrust'] += $part_stats['maxThrust'];
            }
            
            if( $part_stats['maxThrust'] && $part_stats['mass'] ){
                $part_stats['maxTWR'] = ( $part_stats['maxThrust'] / ($part_stats['mass']*9.80665) );
            }
            if( $part_stats['launchThrust'] && $part_stats['mass'] ){
                $part_stats['launchTWR'] = ( $part_stats['launchThrust'] / ($part_stats['mass']*9.80665) );
            }
            $parts[$part_name] = $part_stats;
        }
        
        // Prepare write_data.
        
        $write_data = array();
        $fields = array_keys($part_stats);
        foreach( $parts as $part_name => $part_data ){
            $part_data_temp = array();
            foreach( $fields as $stats_key ){
                $the_val = $part_data[$stats_key];
                if( $the_val === true ){ $the_val = 'true'; }
                $part_data_temp[$stats_key] = SQLite3::escapeString(static::my_escape_sql($the_val));
            }
            $write_data[] = $part_data_temp;
        }
        $write_data_temp = array($fields);
        foreach($write_data as $each_data){
            array_push($write_data_temp,array_values($each_data));
        }
        $write_data = $write_data_temp;
        
//var_dump($write_data);
        
        // Delete all old entries from the table.
        $row_count = static::$parts_db->remove(static::$parts_table_name);
        $db_err = static::$parts_db->get_error();
        
        // Write new entries to table.
        $row_count = static::$parts_db->write(static::$parts_table_name,$write_data,true);
        $db_err = static::$parts_db->get_error();
        
        if(
            ! empty($row_count)
            AND empty($db_err)
        ){
            echo get_called_class().': DB: Success!'.PHP_EOL .'</pre>';
            flush();
        }else{
            // Write failure.
        }
        die('Not for production - please tell the admin.');
    }
    
    static private function find_files($patt=false,$dir=false){
        $return = array();
        if( $patt === false ){ $patt = '/.*/i'; }
        if( $dir === false ){ $dir = dirname($_SERVER['SCRIPT_FILENAME']); }
        foreach( scandir($dir) as $file ){
            if( preg_match('/^\.?\.$/i',$file) ){ continue; }
            $pathname = $dir.'/'.$file;
            $pathname = implode('/',explode('\\',$pathname));
            if( is_readable($pathname) ){
                if( preg_match($patt,$file) ){
                    $return[] = $pathname;
                }elseif(is_dir($pathname)){
                    $return = array_merge( $return, static::find_files($patt,$pathname) );
                }
            }
        }
        return $return;
    }
    
    static public function craft_uploader(){
        $return = '';
        if( ! empty(static::$uploads['kitchen_uploader']['tmp_name']) ){
            $upload = static::$uploads['kitchen_uploader'];
            if( ! empty($upload['error']) ){
                $return .= "ERROR: UPLOAD_ERR_".$upload['error'];
            }else{
                $file = $upload['tmp_name'];
                $filename = $upload['name'];
                if(
                    ! preg_match('/\.craft$/i',$filename)
                    OR ! $filetext = static::valid_craft_text($file)
                ){
                    $return .= "ERROR: Unrecognizable file content or extension.";
                }elseif(
                    ! static::screen_for_public($filetext)
                    OR ! static::screen_for_public($filename)
                ){
                    $return .= "ERROR: Bad words detected. This tool is for all ages.";
                }else{
                    $save_dir = static::$public_uploads;
                    $save_file = $save_dir.'/'.$filename;
                    if( ! is_readable($save_dir) ){ sleep(5); }
                    if( ! is_writable($save_dir) OR ! is_dir($save_dir)  ){
                        die('Can\'t read/write upload directory.');
                    }
                    if( file_exists($save_file) ){
                        $return .= "ERROR: File already exists.";
                    }else{
                        $upload_success = @file_put_contents($save_file,@file_get_contents($file),LOCK_EX);
                        if( $upload_success ){
                            $return .= "Thanks for sharing!";
                            $craft_info = static::get_craft_info($save_file);
                            $craft_info['user'] = @$_SESSION['user']['username'];
                            static::save_craft_info($craft_info,true);
                        }else{
                            $return .= "ERROR: Failed to save uploaded file.";
                        }
                    }
                }
            }
            if( $return ){
                static::$message .= 'Uploader: '.$return.PHP_EOL;
            }
            static::log_uploads();
            @unlink(@$file);
        }
        if( $return ){
            $return = '<p style="border:1px solid red;">'.$return.'</p>';
        }
        $return .= '
        <form name="kitchen_uploader" method="post" enctype="multipart/form-data"><fieldset>
            <h5>Upload a .craft file to the Public Gallery.</h5>
            <input type="hidden" name="MAX_FILE_SIZE" value="'.static::$max_craft_size.'" />
            <input type="file" name="kitchen_uploader" id="kitchen_uploader" />
            <input type="submit" value="Share" />
        </fieldset></form>';
        return $return;
    }
    
    static public function craft_fixer(){
        $class = get_called_class();
        if( isset($_GET['fixer_get']) ){
            if(
                empty($_SESSION[$class]['fixer_file_name'])
                OR empty($_SESSION[$class]['fixer_file_text'])
            ){
                die('Missing session data.');
            }
            $filename = $_SESSION[$class]['fixer_file_name'];
            $filetext = $_SESSION[$class]['fixer_file_text'];
            unset(
                $_SESSION[$class]['fixer_file_name']
                ,$_SESSION[$class]['fixer_file_text']
            );
            $filename = preg_replace('/;/i', '_', $filename);
                // No semi-colons!  Is there some way to encode/escape them?
            header( 'Content-Description: File Transfer' );
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Disposition: attachment; filename="'.$filename.'"');
            header( 'Content-Transfer-Encoding: binary' );
            header( 'Expires: 0' );
            header( 'Cache-Control: must-revalidate' );
            header( 'Pragma: public' );
            header( 'Content-Length: ' .mb_strlen($filetext) );
            echo $filetext;
            exit;
        }
        
        $return = '';
        if( isset(static::$uploads['kitchen_fixer']['tmp_name']) ){
            set_time_limit(ini_get('max_execution_time')+90);
            $microtime = microtime(true);
            $upload = static::$uploads['kitchen_fixer'];
            unset(static::$uploads['kitchen_fixer']);
            $file = $upload['tmp_name'];
            $filename = $upload['name'];
            $filetext = static::valid_craft_text($file);
            if( $filetext ){
                echo 'Reading the file...<br/>';
                flush();
                $data = static::$cfg_node->cfg_to_array($filetext);
                
                // Remove duplicates.
                $data = static::remove_duplicate_parts($data); // array($data,$info)
                if( $data[1] ){
                    $suffixed_filename = pathinfo($filename,PATHINFO_FILENAME).'_FIXED';
                    if( pathinfo($filename,PATHINFO_EXTENSION) ){
                        $suffixed_filename .= '.'.pathinfo($filename,PATHINFO_EXTENSION);
                    }
                    $filename = $suffixed_filename;
                }
                $return = $data[1];
                $data = $data[0];
                    
                if( @$_POST['stockify'] ){
                    // Remove add-ons.
                    $data = static::remove_addon_parts($data);
                    if( $data[1] ){
                        $suffixed_filename = pathinfo($filename,PATHINFO_FILENAME).'_STOCK';
                        if( pathinfo($filename,PATHINFO_EXTENSION) ){
                            $suffixed_filename .= '.'.pathinfo($filename,PATHINFO_EXTENSION);
                        }
                        $filename = $suffixed_filename;
                    }
                    $return = $data[1];
                    $data = $data[0];
                }
                
                // Fixing done.
                $filetext = static::$cfg_node->array_to_cfg($data);
                if( $return !== '' ){ // Changes were made.
                    echo '<p>Here\'s your processed file: <a title="Download '.$filename.'." href="?fixer_get=true">'.$filename.'</a></p><p><a title="Return" href="?">Return</a></p>';
                    flush();
                    
                    $_SESSION[$class] = array();
                    $_SESSION[$class]['fixer_file_name'] = $filename;
                    $_SESSION[$class]['fixer_file_text'] = $filetext;

                    // Gank a copy for us.
                    $save_dir = './_'.$class.'_fixed_ships';
                    if( ! is_readable($save_dir) ){ sleep(5); }
                    if( is_writable($save_dir) && is_dir($save_dir)  ){
                        $save_file = $save_dir.'/'.$filename;
                        $saved = @file_put_contents($save_file, $filetext,LOCK_EX);
                        if( $saved ){
                            static::$output_file = array($save_file,$filename);
                        }
                    }
                    
                    die();
                }else{
                    $return .= 'No problems detected.'.PHP_EOL;
                }
            }else{
                $return .= "ERROR: Unrecognizable file content.".PHP_EOL;
            }
            static::$message .= "Fixer: ".PHP_EOL.$return.' in '.round(microtime(true)-$microtime,2).' seconds.';
            static::log_uploads();
        }
        if( $return === true ){
            $return = '';
        }
        if( $return ){
            $return = '<pre style="border:1px solid red;">'.$return.'</pre>';
        }
        $return .= '<form name="kitchen_fixer" method="post" enctype="multipart/form-data"><fieldset>
            To fix a .craft file, click BROWSE to choose your file, select your options, then click REPAIR.<br/>
            <br/>
            <input type="hidden" name="MAX_FILE_SIZE" value="1048000" />
            <input type="file" name="kitchen_fixer" id="kitchen_fixer" onChange="" /><br/>
            <br/>
            <span style="border:1px solid black; display:inline-block;">
                <label style="float:right;" for="stockify">Check here to remove any add-on parts. <small>(<strong>WARNING:</strong> Anything mounted to them is also removed.)</small></label>
                <input type="checkbox" name="stockify" id="stockify" />
            </span><br/>
            <br/>
            <input type="submit" value="Repair" />
        </fieldset></form>';
        return $return;
    }
    
    static private function mark_part_tree($data, $part_id){
        foreach( $data as $key => $val ){
            if( ! isset($val['part#0']) ){ continue; }
            if(
                $val['part#0'] === $part_id
                && ! @$val['MARKED']
            ){
                $data[$key]['MARKED'] = true;
                foreach( $data as $key_2 => $val_2 ){
                    if( ! isset($val_2['part#0']) ){ continue; }
                    if( preg_match('/link|sym/i', $key_2) ){
                        return static::mark_part_tree($data, $val_2);
                    }
                }
            }
        }
        return $data;
    }

    static private function remove_addon_parts($data){ // "Stockifier"
        echo 'Checking for add-on parts...<br/>';
        flush();
        $return = '';
        $addons = array();
        if( ! static::$game_data ){
            static::get_game_data();
        }
        foreach($data as $ship_key => $ship_part){
            if( ! isset($ship_part['part#0']) ){ continue; }
            $part_id = $ship_part['part#0'];
            $part_name = preg_replace('/_\d+$/i','',$ship_part['part#0']);
            $part_name_flat = preg_replace('/\./i','_',$part_name);
                // Part names can have periods in the .craft file (part = ), but are SOMETIMES (usually) found as underscores in part.cfg (name = ) - Hulk smash(flatten).
            $part_name_patt = preg_quote($part_name_flat,'/');
            
            $is_stock = false;
            
            if(
                ! empty(static::$game_data['parts'])
                AND array_key_exists($part_name_flat, static::$game_data['parts'])
            ){
                $game_part = static::$game_data['parts'][$part_name_flat];
                if(
                    $game_part['vendor'] === 'Squad'
                    or $game_part['vendor'] === 'NASAmission'
                ){
                    $is_stock = true;
                }
            }
            
            
            if( ! $is_stock ){
                if( ! count($addons) ){ // First.
                    echo 'Add-on parts found!<br/>';
                    flush();
                    $return = true;
//                    $return .= "\tAdd-on parts found!\r\n";
                }
                $addons[] = $ship_part['part#0'];
                $data = static::mark_part_tree($data,$ship_part['part#0']);
            }
        }

        foreach($data as $ship_key => $ship_part){
            if( ! isset($ship_part['part#0']) ){ continue; }
            if( isset($ship_part['MARKED']) ){
                echo '&nbsp;&nbsp;Removing: '.$ship_part['part#0'].'<br/>';
                flush();
//                $return .= "\t\t".$ship_part['part#0']."\r\n";
                unset($data[$ship_key]);
            }else{
                foreach( $ship_part as $part_key => $part_val ){
                    if( in_array($part_val, $addons) ){
                        unset($data[$ship_key][$part_key]);
                    }
                }
            }
        }
        if( $addons_count = count($addons) ){
            echo $addons_count.' add-on parts were removed.<br/>';
            flush();
//            $return .= "\t".$addons_count." add-on parts were removed.\r\n";
        }
        return array($data,$return);
    }
        
    static private function remove_duplicate_parts($data){
        echo 'Checking for duplicates...<br/>';
        flush();
        $return = '';
        $parts = array();
        $duplicates = array();
        foreach($data as $data_key => $data_val){
            if( ! is_array($data_val) OR  !isset($data_val['part#0']) ){ continue; }
            $parts[] = $data_val;
            if( isset($data[$data_key]['DUPLICATE']) ){ continue; }
            foreach($data as $data2_key => $data2_val){
                if( ! is_array($data2_val) OR !isset($data2_val['part#0']) ){ continue; }
                if( $data2_key === $data_key ){ continue; } // Self.
                if($data2_val['part#0'] === $data_val['part#0']){ // Duplicate.
                    $data[$data2_key]['DUPLICATE'] = true;
                    if( ! in_array($data2_key, $duplicates) ){
                        if( ! count($duplicates) ){ // First.
                            echo 'Duplicates found!<br/>';
                            flush();
                            $return = true;
//                            $return .= "\tDuplicates found!\r\n";
                        }
                        $duplicates[] = $data2_key;
                    }
                }
            }
        }
        foreach( $duplicates as $val ){
            echo '&nbsp;&nbsp;Removing: '.$data[$val]['part#0'].'<br/>';
            flush();
//            $return .= "\t\t".$data[$val]['part#0']."\r\n";
            unset($data[$val]);
        }
        if( $dup_count = count($duplicates) ){
            echo "\t".$dup_count." duplicates were removed.\r\n";
            flush();
//            $return .= "\t".$dup_count." duplicates were removed.\r\n";
        }
        return array($data,$return);
    }
    
    static public function valid_craft_text($file=''){
        if(
            ! is_readable($file)
            OR is_dir($file)
            OR @filesize($file) > static::$max_craft_size
            OR @filesize($file) < 250
        ){ return false; }
        $file_type = @finfo_file(@finfo_open(FILEINFO_MIME_TYPE), $file);
        if( $file_type !== 'text/plain' ){ return false; }
        $file_text = @file_get_contents($file);
        if( ! $file_text ){ return; }
        $lines = explode("\n",$file_text);
        $validate_patts = array(
            'ship = [^\{\}]+'
            ,'version = [^\{\}]+'
            ,'description =[^\{\}]*'
            ,'type = (SPH|VAB)'
            ,'size =[^\{\}]*'
            ,'PART'
            ,'\{'
            ,'\tpart = .+'
            ,'\tpartName = .+'
            ,'\tpos = .+'
            ,'\tattPos = .+'
            ,'\tattPos0 = .+'
            ,'\trot = .+'
            ,'\tattRot = .+'
            ,'\tattRot0 = .+'
            ,'\tmir = .+'
            ,'\tsymMethod = .+'
            ,'\tistg = .+'
            ,'\tdstg = .+'
            ,'\tsidx = .+'
            ,'\tsqor = .+'
            ,'\tattm = .+'
            ,'\tmodCost = .+'
            ,'\tmodMass = .+'
            ,'\tmodSize = .+'
        );
        // Older versions don't have "description" (1 less).
        $lines_to_check = count($validate_patts)-9;
        $i = 0;
        foreach($lines as $line){
            if( ++$i > $lines_to_check ){ break; }
            $found_match = false;
            foreach($validate_patts as $patt){
                $patt = '/^'.$patt.'$/';
                if( preg_match($patt,rtrim($line)) ){
                    $found_match = true;
                    break;
                }
            }
            if( ! $found_match ){ return false; }
        }
        return $file_text;
    }
    
    static private function screen_for_public($text){
        if( ! $text ){ return; }
        $return = true;
        $bad_words = array(
            'testickle', 'nigg', 'wigger', 'fuck', 'shit', 'cunt', 'bitch'
        );
        foreach( $bad_words as $word ){
            $word = preg_quote($word,'/');
            if(
                preg_match('/'.$word.'/i',$text)
            ){
                $return = false;
            }
        }
        return $return;
    }
    
/* END OF CLASS */
}
?>