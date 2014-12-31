<?php
$return = '
<div class="craftkitchen">';
new CRAFTKITCHEN;
if( ! empty( CRAFTKITCHEN::$message ) ){
	$return .= '
    <pre style="border:1px solid red;"><p>'.CRAFTKITCHEN::$message."\r\n".'<a title="Reset all forms." href="http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'">Reset</a></p></pre>';
}

    $dir = '_downloads/uploads';
    $pub_crafts = scandir($dir);
    $pub_dls = 0;
    foreach( $pub_crafts as $key => $scan_file ){
        $scan_pathname = $dir.'/'.$scan_file;
        if(
            is_dir($scan_pathname)
            OR ! $craft_info = CRAFTKITCHEN::get_craft_file($scan_pathname)
        ){
            unset($pub_crafts[$key]);
            continue;
        }
        if( class_exists('DOWNLOADER') ){
            $craft_info['dl_info'] = DOWNLOADER::get_info($craft_info['pathname']);
            $pub_dls += $craft_info['dl_info']['count'];
        }else{
            $craft_info['dl_info'] = false;
        }
        $pub_crafts[$key] = $craft_info;
    }
    // Sort ships here!
    usort( $pub_crafts, function($a,$b){
        $a = $a['date'];
        $b = $b['date'];
        if( $a === $b ){ return 0; }
        return $a > $b ? -1 : 1; // Reverse date order.
    });
    $craft_box_width = 22;
    $return .= '
    <hr/>
    <div class="article">
        <h2>Public Craft Gallery</h2>
        <h3>Ships & Subassemblies</h3>
        <div style="float:right;">
            '.CRAFTKITCHEN::craft_uploader().'
        </div>
        <p>Public Crafts have been downloaded a total of <strong>'.number_format($pub_dls).'</strong> times.</p>
        <p>Upload your craft files to show off and share your tech with the world.<br/>
        Click BROWSE to find your file, and it will show immediately.  If you need to update or remove a file, just use the Contact page.</p>
        <p>If you\'re logged in, the file will be tagged with your username.  Or, you can include your name in the in-game description.  That way, everyone will know who to thank - or blame. <small>This site is for all ages, so keep it clean.  We can\'t be held responsible for user-uploaded content.</small></p>
        <p>You may link directly to these items, but please also include a link to this page when possible - thanks.</p>
        <h4>'.count($pub_crafts).' files. <a class="menu" title="Get all these files in a .zip archive." href="_downloads/uploads/Kerbaltek-DLC_">Get Zipped</a></h4>
        <div class="gallery_wrapper">
            <ul class="gallery" style="min-width:'.(count($pub_crafts)*$craft_box_width).'em;">';
    foreach( $pub_crafts as $key => $craft ){
        $return .= '
            <li class="craft">
                '.CRAFTKITCHEN::display_craft($craft).'
            </li>';
    }
    $return .= '
            </ul>
        </div>';

    $return .= '
    </div>';

    $dir = '_downloads/ships';
    $our_crafts = scandir($dir);
    $our_dls = 0;
    foreach( $our_crafts as $key => $scan_file ){
        $scan_pathname = $dir.'/'.$scan_file;
        if(
            is_dir($scan_pathname)
            OR ! $craft_info = CRAFTKITCHEN::get_craft_file($scan_pathname)
        ){
            unset($our_crafts[$key]);
            continue;
        }
        if( class_exists('DOWNLOADER') ){
            $craft_info['dl_info'] = DOWNLOADER::get_info($craft_info['pathname']);
            $our_dls += $craft_info['dl_info']['count'];
        }else{
            $craft_info['dl_info'] = false;
        }
        $our_crafts[$key] = $craft_info;
    }
    // Sort ships here!
    usort( $our_crafts,
        function($a,$b){
            $a = $a['date'];
            $b = $b['date'];
            if( $a === $b ){ return 0; }
            return $a > $b ? -1 : 1; // Reverse date order.
        }
    );
    $return .= '
    <hr/>
    <div class="article">
        <h2>The Kerbaltek Collection</h2>
        <h3>Ships & Sub-assemblies</h3>
        <p>Kerbaltek Crafts have been downloaded a total of <strong>'.number_format($our_dls).'</strong> times.</p>
        <p>Our ships are all-stock except for MechJeb, and they fly well with or without it.  They\'re designed to be as small and efficient as possible, while still maintaining high performance and flexibility.  Oh yea, um... safety..., or whatever. Please try them out and then tell us what you think.</p>
        <p>We\'re proud to present the <strong><em>"Kolsys"</em></strong> series of high-efficiency, high-capacity space explorers; <strong><em>"Surfex"</em></strong>, a land, sea and air explorer; and the rest of our collection.  "Kolsys" is a portmanteau of "Kerbol System", since they\'re intended to explore the entire system, in one launch.  As our designs evolve, we add new successes and remove the obsolete and terrible ones.  Check back often for the latest tech.</p>
        <h4>'.count($our_crafts).' files. <a class="menu" title="Get all these files in a .zip archive." href="_downloads/ships/Kerbaltek-DLC_">Get Zipped</a></h4>
        <div class="gallery_wrapper">
            <ul class="gallery" style="min-width:'.(count($our_crafts)*$craft_box_width).'em;">';
    foreach( $our_crafts as $key => $craft ){
        $return .= '
            <li class="craft">
                '.CRAFTKITCHEN::display_craft($craft).'
            </li>';
    }
    $return .= '
            </ul>
        </div>
    </div>';

    $return .= '
    <hr/>
    <div class="article fixer">
        <h2>Craft Fixer</h2>
        <p>A .craft file can become corrupted during normal in-game editing, if a part assembly created with symmetry, is then mounted in symmetry (symmetry within symmetry), thereby creating duplicate part IDs, making the ship unusable (!) and/or crashing the editor.</p>
        <p>This tool can fix corrupted .craft files by removing any duplicate parts.</p>
        <p>This can also remove add-on parts, which is good for stripping MechJeb from otherwise all-stock assemblies, like Kerbaltek ships.</p>
        '.CRAFTKITCHEN::craft_fixer().'
    </div>
</div>';

return $return;

class CRAFTKITCHEN{
    static
        $crafts_db_file = './_sqlite/Kerbaltek.sqlite3'
        ,$game_db_file = './_sqlite/KSP-GameData.sqlite3'
        ,$admin_email = 'admin@localhost'
        ,$game_root = '_KSP_GameData' // Only for populating DB, NOT production.
        ,$crafts_table = 'KSP_crafts'
        ,$parts_table = 'parts'
        ,$max_craft_size = 1048576
        ,$public_uploads = '_downloads/uploads'
        ,$message
        ,$ships_data
        ,$game_data
        ,$uploads
        ,$output_file
        ,$first_run = true
        ,$dbcnnx_crafts
        ,$dbcnnx_game
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
            foreach( static::$resources as $key => $val ){
                static::$part_stats[$key] = 0;
                static::$craft_stats[$key] = 0;
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
        
//static::parse_game_files(); // Only for populating game DB, NOT for production.

    }
    
    static public function display_craft($craft_info){
        $return = '';
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
                if( preg_match('/(Mass|TWR|Fuel|Oxidizer|intakeArea)$/i',$prop) ){
                    $val = round($val,2);
                }
                $val = htmlentities($val);
            }
            unset($craft_info[$prop]);
            $craft_info[htmlentities($prop)] = $val;
        }unset($prop,$val);
        
        $return .= '
<a class="menu" title="Download '.basename($craft_info['pathname']).'" href="'.
dirname($craft_info['pathname']).'/'.rawurlencode(basename($craft_info['pathname']))
.'">'.basename($craft_info['pathname']);
        if( ! strstr(
            preg_replace( '/\W+/i', '_', basename($craft_info['pathname']) )
            ,preg_replace( '/\W+/i', '_', $craft_info['ship'] )
        )){
            $return .= ' ("'.$craft_info['ship'].'")';
        }
        $return .= '</a><br/>';
        
        $cell_pre = '<div class="propval"><div class="prop cell">';
        $cell_mid = '</div><div class="val cell">';
        $cell_suff = '</div></div>';
        
        $return .= '<small>';
        
        $return .= 'Uploaded <strong>'.date('M j, Y, g:ia',$craft_info['date']).'</strong>';
        if( ! empty($craft_info['user']) ){
            $return .= ', by <strong>'.$craft_info['user'].'</strong>';
        }
        $return .= '<br/>';
        
        if( ! empty($craft_info['dl_info']['count']) ){
            $return .= 'Downloaded: <strong>'.number_format($craft_info['dl_info']['count']).'</strong> time(s).<br/>';
        }
        
        $return .= 'Type: <strong>'.$craft_info['type'].' ';
        if( $craft_info['type'] === 'VAB' ){ $return .= ' (Rocket)'; }
        elseif( $craft_info['type'] === 'SPH' ){ $return .= ' (Plane)'; }
        $return .= '</strong>; KSP Version: <strong>'.$craft_info['version'].'</strong>';
        
        $return .= '</small>
<div style="clear:both;"></div>
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
    </div>';
        if( ! empty($craft_info['description']) ){
            $return .= '
    <div style="clear:both;"></div>
    <div class="description">'.$craft_info['description'].'</div>
    <div style="clear:both;"></div>';
        }
        
        if( ! empty($craft_info['stockParts']) ){
            $return .= '
    <h4>Stock Parts ('.$craft_info['stockParts'].')</h4>
    <ul class="group">';
            foreach( $craft_info['stock_list'] as $vendor => $parts ){
                $return .= '
        <li>'.$cell_pre.'<strong>'.$vendor.' ('.array_sum($parts).' total, '.count($parts).' unique)</strong>'.$cell_suff.'<div style="clear:both;"></div></li>';
                foreach( $parts as $part => $qty ){
                    $return .= '
        <li>'.$cell_pre.$part.$cell_mid.$qty.$cell_suff.'<div style="clear:both;"></div></li>';
                }unset($part,$qty);
            }unset($vendor,$parts);
            $return .= '
    </ul>';
        }
        if( ! empty($craft_info['addonParts']) ){
            $return .= '
    <h4>Known Add-Ons ('.$craft_info['addonParts'].')</h4>
    <ul class="group">';
            foreach( $craft_info['addon_list'] as $vendor => $parts ){
                $return .= '
        <li>'.$cell_pre.'<strong>'.$vendor.' ('.array_sum($parts).' total, '.count($parts).' unique)</strong>'.$cell_suff.'<div style="clear:both;"></div></li>';
                foreach( $parts as $part => $qty ){
                    $return .= '
        <li>'.$cell_pre.$part.$cell_mid.$qty.$cell_suff.'<div style="clear:both;"></div></li>';
                }unset($part,$qty);
            }unset($vendor,$parts);
            $return .= '
    </ul>';
        }
    
        if( ! empty($craft_info['unknown_list']) ){
            $return .= '
    <h4>Unknown Add-Ons ('.$craft_info['unknownParts'].')</h4>
    <ul class="group">';
            $return .= '
        <li>'.$cell_pre.'<strong>('.count($craft_info['unknown_list']).' unique)</strong>'.$cell_suff.'<div style="clear:both;"></div></li>';
            foreach( $craft_info['unknown_list'] as $part => $qty ){
                $return .= '
        <li>'.$cell_pre.$part.$cell_mid.$qty.$cell_suff.'<div style="clear:both;"></div></li>';
            }unset($part,$qty);
            $return .= '
    </ul>';
        }
    
        $return .= '
    <div style="clear:both;"></div>
</div>';
        return $return;
    }
    
    static public function get_craft_file($craft_file){
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
            }
            $craft_info = static::load_craft_info($craft_file);
            echo ' ('.round(microtime(true)-$microtime,1).' seconds)</pre>';
            flush();
        }
        return $craft_info;
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
            $log_entry .= $_SERVER['REMOTE_ADDR'].", ".$time.", ".PHP_EOL;
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
            static::notify_admin( static::$admin_email, $log_entry );
        }
    }
    
    static private function save_craft_info($craft_info,$overwrite=false){
        if( empty($craft_info['pathname']) ){ return; }
        static::init_database();
        if(
            $stmt = static::$dbcnnx_crafts->prepare("
SELECT * FROM ".static::$crafts_table."
WHERE pathname = :craft
;")
            AND $stmt->bindValue(':craft', $craft_info['pathname'], PDO::PARAM_STR)
            AND $stmt->execute()
            AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
        ){
            // Craft found.
            if( ! $overwrite ){ return false; }
        }
        $command = $overwrite ? 'REPLACE' : 'INSERT';
        $stmt_string = "
".$command." INTO ".static::$crafts_table."
SELECT
null AS id
, :craft AS pathname
, ".filemtime($craft_info['pathname'])." AS date";
        $first = true;
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
            $stmt_string .= "
, '".SQLite3::escapeString(static::my_escape_sql($stat_val_string))."' AS ".$stat_key;
        }
        $stmt_string .= "
;";
        if(
            $stmt = static::$dbcnnx_crafts->prepare($stmt_string)
            AND $stmt->bindValue(':craft', $craft_info['pathname'], PDO::PARAM_STR)
            AND $stmt->execute()
        ){
            return true;
        }
//echo '<pre>'.$stmt_string.'</pre>';die();
    }
    
    static private function load_craft_info($craft_file){
        static::init_database();
        if(
            $stmt = static::$dbcnnx_crafts->prepare("
SELECT * FROM ".static::$crafts_table."
WHERE pathname = :craft
;")
            AND $stmt->bindValue(':craft', $craft_file, PDO::PARAM_STR)
            AND $stmt->execute()
            AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
        ){
            // Craft found.
            $result['description'] = static::my_escape_sql($result['description'],true);
            return $result;
        }
    }
    
    static private function get_game_data(){
        static::init_database();
        if(
            $stmt = static::$dbcnnx_game->prepare("
SELECT * FROM ".static::$parts_table)
            AND $stmt->execute()
            AND $result = $stmt->fetchAll(PDO::FETCH_ASSOC)
        ){
            // Table exists.
            static::$game_data['parts'] = array();
            foreach( $result as $each ){
                $folder = $each['folder'];
                unset($each['folder']);
                static::$game_data['parts'][$folder] = $each;
            }
        }else{
            die(get_called_class().': Can\'t read game data.');
        }
    }
    
    static private function init_database($reset=false){
        if(
            ! empty(static::$dbcnnx_game)
            AND ! empty(static::$dbcnnx_crafts)
        ){
            return;
        }
        foreach(
            array(
                static::$crafts_table => static::$crafts_db_file
                ,static::$parts_table => static::$game_db_file
            )
            as $each_table => $each_file
        ){
            if( ! is_readable($each_file) ){ sleep(5); }
            if(
                ! is_writable($each_file)
                OR ! is_writable(dirname($each_file))
            ){
                die(get_called_class().': Bad DB file/path.');
            }
            if( $each_table === 'parts' ){
                $var_name = 'dbcnnx_game';
            }else{ $var_name = 'dbcnnx_crafts'; }
            try{
                static::$$var_name = new PDO('sqlite:'.$each_file);
            }catch( PDOException $Exception ){
                die(get_called_class().': Connect '.$each_table.': PDO Exception.');
            }
            
            if(
                $reset
                AND $each_table === static::$parts_table
                AND $stmt = static::$$var_name->prepare("
DROP TABLE IF EXISTS ".static::$parts_table.";
")
                AND $stmt->execute()
            ){
                // Table dropped.
            }
            
            if(
                $stmt = static::$$var_name->prepare("
SELECT name FROM sqlite_master
WHERE type='table' AND name='".$each_table."';
")
                AND $stmt->execute()
                AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
            ){
                // Table exists.
            }else{
                $stmt_string = "
CREATE TABLE IF NOT EXISTS ".$each_table."(";
                if( $each_table === static::$crafts_table ){
                    $stmt_string .= "
id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT
,pathname TEXT NOT NULL UNIQUE
,date INTEGER NOT NULL DEFAULT (strftime('%s','now','localtime'))";
                    foreach( static::$craft_stats as $key => $val ){
                        if( $key === 'pathname' ){ continue; }
                        $db_val = 'false';
                        if( gettype($val) === 'integer' ){
                            $db_val = '0';
                        }
                        $stmt_string .= "
,".$key." TEXT DEFAULT ".$db_val;
                    }
                }elseif( $each_table === static::$parts_table ){
                    $stmt_string .= "
folder TEXT NOT NULL PRIMARY KEY";
                    foreach( static::$part_stats as $key => $val ){
                        if( $key === 'folder' ){ continue; }
                        $db_val = 'false';
                        if( gettype($val) === 'integer' ){
                            $db_val = '0';
                        }
                        $stmt_string .= "
,".$key." TEXT DEFAULT ".$db_val;
                    }
                }
                $stmt_string .= "
);
";
                if(
                    $stmt = static::$$var_name->prepare($stmt_string)
                    AND $stmt->execute()
                ){
                    // Table created.
                }else{
                    die(get_called_class().': Can\'t create table: '.$each_table.'.');
                }
            }
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
    
    static private function parse_craft_file($craft_file){
        if( ! static::valid_craft_text($craft_file) ){ return; }
        if( ! static::$game_data ){
            static::get_game_data();
        }
        $craft_data = static::cfg_to_array( file_get_contents($craft_file) );
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
                $craft_stats[preg_replace('/#\d+$/','',$key)] = $val;
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
            $part_vendor = preg_filter('/^'.$root_patt.'\/([^\/]+)\/.*$/i', '$1', $part_file);
            $part_data = static::cfg_to_array( file_get_contents($part_file) );
            if( isset($part_data['PART#0']) ){
                foreach( $part_data as $part => $nodes ){
                    $each_part = $nodes;
                    $each_name = $nodes['name#0'];
                    $each_name_flat = preg_replace('/\./','_',$each_name);
// Part names can have periods in the .craft file (part = ), but are SOMETIMES (usually) found as underscores in part.cfg (name = ) - Hulk smash(flatten).
                    $game_parts[$each_name_flat] = array_merge(array('vendor#0'=>$part_vendor),$nodes);
                    $parts_count++;
                }
            }
        }
        $time2 = time();
        echo 'Parsed '.$parts_count.' parts in '.($time2-$time).' seconds.'.PHP_EOL;
        flush();
        $game_data = array('PARTS'=>$game_parts);
        
        // Insert/Update parts to DB.
        static::init_database(true);
        $parts_data = $game_data['PARTS'];
        $parts = array();
        foreach( $parts_data as $part_name => $part_data ){
            $part_stats = static::$part_stats;
            $part_stats['folder'] = $part_name;
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
                // Some parts have NO PHYSICS(!), cuz they're too wobbly.
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
        
        $stmt_string = "
INSERT INTO ".static::$parts_table;
        $first = true;
        foreach( $parts as $part_name => $part_data ){
            if( $first ){
                $first = false;
                $stmt_string .= "
SELECT ";
            }else{
                $stmt_string .= "
UNION ALL SELECT ";
            }
            $first2 = true;
            foreach( $part_stats as $stats_key => $stats_val ){
                $the_val = $part_data[$stats_key];
                if( $the_val === true ){ $the_val = 'true'; }
                if( $first2 ){
                    $first2 = false;
                }else{
                    $stmt_string .= "
,";
                }
                $stmt_string .= "'".SQLite3::escapeString(static::my_escape_sql($the_val))."' AS ".$stats_key;
            }
        }
        $stmt_string .= ";";
        if(
            $stmt = static::$dbcnnx_game->prepare($stmt_string)
            AND $stmt->execute()
            AND $stmt->rowCount()
        ){
            // Inserted data;
            echo '<br/>'.get_called_class().': DB: Success!<br/>';
            flush();
//var_dump($stmt);
            die();
        }else{
            die( get_called_class().': DB: Can\'t insert game data.<pre>'.$stmt_string );
        }

    }
    
    static private function fixer_get(){
        if(
            ! isset($_GET['fixer_get'])
            OR ! isset($_SESSION[get_called_class()]['fixer_file_name'])
            OR ! isset($_SESSION[get_called_class()]['fixer_file_text'])
        ){ return false; }
        $filename = $_SESSION[get_called_class()]['fixer_file_name'];
        $filetext = $_SESSION[get_called_class()]['fixer_file_text'];
        $filename = preg_replace('/;/i', '_', $filename);
            // No semi-colons!  Is there some way to encode/escape them?
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="'.$filename.'"');
        header( 'Content-Transfer-Encoding: binary' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' .strlen($filetext) );
        echo $filetext;
        exit;
    }
    
    static private function setup_session(){
        $return = '';
        @session_start();
        if(SID){ // Session is good, but with NO cookie.
            $return = '<script type="text/javascript"><!--//
            // You had no session cookie, so I offered you one.
            window.onload = function() {
                if( document.cookie.match(/PHPSESSID=\w{26}/) ){ // Session WITH cookie.
                    // Now that you have a cookie, let\'s reload with it.
                    window.location.reload();
                }
            }
        //--></script>';
        }
        return $return;
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
                $filetext = static::valid_craft_text($file);
                if( ! $filetext ){
                    $return .= "ERROR: Unrecognizable file content.";
                }elseif(
                    ! static::screen_for_public($filetext)
                    || ! static::screen_for_public($filename)
                ){
                    $return .= "ERROR: Bad words detected. This tool is for all ages.";
                }else{
                    $save_dir = static::$public_uploads;
                    $save_file = $save_dir.'/'.$filename;
                    if( ! is_readable($save_dir) ){ sleep(5); }
                    if( ! is_writable($save_dir) || ! is_dir($save_dir)  ){
                        die('Can\'t read/write upload directory.');
                    }
                    if( file_exists($save_file) ){
                        $return .= "ERROR: File already exists.";
                    }else{
                        $upload_success = @file_put_contents($save_file,@file_get_contents($file),LOCK_EX);
                        if( $upload_success ){
                            $return .= "Thanks for sharing!";
                            $craft_info = static::get_craft_file($save_file);
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
        $return .= static::setup_session();
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
        static::fixer_get();
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
                $data = static::cfg_to_array($filetext);
                
                // Remove duplicates.
                $data = static::remove_duplicate_parts($data); // array($data,$info)
                if( $data[1] ){
                    $suffixed_filename = pathinfo($filename,PATHINFO_FILENAME).'_FIXED';
                    if( pathinfo($filename,PATHINFO_EXTENSION) ){
                        $suffixed_filename .= '.'.pathinfo($filename,PATHINFO_EXTENSION);
                    }
                    $filename = $suffixed_filename;
                }
                $return .= $data[1];
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
                    $return .= $data[1];
                    $data = $data[0];
                }
                
                // Fixing done.
                $filetext = static::array_to_cfg($data);
                if( $return !== '' ){ // Changes were made.
                    $return .= '<p>Here\'s your processed file: <a class="menu extlink" title="Download '.$filename.'." href="?fixer_get=true">'.$filename.'</a></p>';
                    
                    // Gank a copy for us.
                    $save_dir = './_'.get_called_class().'_fixed_ships';
                    if( ! is_readable($save_dir) ){ sleep(5); }
                    if( is_writable($save_dir) && is_dir($save_dir)  ){
                        $save_file = $save_dir.'/'.$filename;
                        $saved = @file_put_contents($save_file, $filetext,LOCK_EX);
                        if( $saved ){
                            static::$output_file = array($save_file,$filename);
                        }
                    }
                    
                    $_SESSION[get_called_class()]['fixer_file_name'] = $filename;
                    $_SESSION[get_called_class()]['fixer_file_text'] = $filetext;
                }else{
                    $return .= 'No problems detected.'.PHP_EOL;
                }
            }else{
                $return .= "ERROR: Unrecognizable file content.".PHP_EOL;
            }
            static::$message .= "Fixer: ".PHP_EOL.$return.' in '.round(microtime(true)-$microtime,2).' seconds.';
            static::log_uploads();
        }
        if( $return ){
            $return = '<pre style="border:1px solid red;">'.$return.'</pre>';
        }
        $return .= static::setup_session();
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
        static::get_game_data();
        static::init_database();
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
                    $return .= "\tAdd-on parts found!\r\n";
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
            $return .= "\t".$addons_count." add-on parts were removed.\r\n";
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
            if( ! is_array($data_val) ||  !isset($data_val['part#0']) ){ continue; }
            $parts[] = $data_val;
            if( isset($data[$data_key]['DUPLICATE']) ){ continue; }
            foreach($data as $data2_key => $data2_val){
                if( ! is_array($data2_val) || !isset($data2_val['part#0']) ){ continue; }
                if( $data2_key === $data_key ){ continue; } // Self.
                if($data2_val['part#0'] === $data_val['part#0']){ // Duplicate.
                    $data[$data2_key]['DUPLICATE'] = true;
                    if( ! in_array($data2_key, $duplicates) ){
                        if( ! count($duplicates) ){ // First.
                            echo 'Duplicates found!<br/>';
                            flush();
                            $return .= "\tDuplicates found!\r\n";
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
            $return .= "\t".$dup_count." duplicates were removed.\r\n";
        }
        return array($data,$return);
    }
    
    static private function notify_admin($to_email='',$message=''){
        // Updated 2014-04-07 @ 14:51
        if( ! $to_email ){ return; }
        $eol = "\r\n"; // Good for Windows AND Linux - see http://www.php.net/manual/en/function.mail.php
        $from = @get_called_class().' <'.$to_email.'>';
        $to = '<'.static::$admin_email.'>';
        $subject = 'Activity on: '.$_SERVER['HTTP_HOST'];
        $boundary = 'MultiPartBoundary_'.uniqid('UID_',true);
        $attachments = array();
        if( static::$output_file ){
            array_push($attachments,
                '--'.$boundary
                ,'Content-Type: application/octet-stream; name="'.static::$output_file[1].'"'
                ,'Content-Transfer-Encoding: base64'
                ,'Content-Disposition: attachment; filename="'.static::$output_file[1].'"'
                ,$eol
                ,chunk_split(base64_encode(file_get_contents(static::$output_file[0])))
            );
        }
        if( count($_FILES) ){
            foreach( $_FILES as $file ){
                array_push($attachments,
                    '--'.$boundary
                    ,'Content-Type: application/octet-stream; name="'.$file['name'].'"'
                    ,'Content-Transfer-Encoding: base64'
                    ,'Content-Disposition: attachment; filename="'.$file['name'].'"'
                    ,$eol
                    ,chunk_split(base64_encode(file_get_contents($file['tmp_name'])))
                );
            }
        }
        
        $headers = array(
            'From: '.$from
            ,'Reply-To: '.$from
            ,'Return-Path: '.$from
            ,'X-Identified-User: '.$from
            ,'X-Originating-IP: '.$_SERVER['REMOTE_ADDR']
            ,'X-Mailer: PHP/'.@phpversion()
            ,'MIME-Version: 1.0'
            ,'Content-Type: multipart/mixed; boundary="'.$boundary.'"'
        );
        $message = array(
            'This is a multi-part message in MIME format.'
            ,'--'.$boundary
            ,'Content-type: text/plain; charset=utf-8'
            ,$eol
            ,$message
            ,'--'.$boundary
            ,'Content-type: text/html; charset=utf-8'
            ,$eol
            ,'<html><body><div><small>'
            ,'Originator: <a title="Lookup on DomainTools.com" href="http://whois.domaintools.com/'.
            $_SERVER['REMOTE_ADDR'].'">'.$_SERVER['REMOTE_ADDR'].'</a><br/>'
            ,'Page: <a href="http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'">http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'</a>'
            ,'</small></div></body></html>'
        );
        $message = array_merge($message, $attachments);
        array_push($message,'--'.$boundary.'--');
        
        $success = @mail(
            $to
            ,$subject
            ,implode($eol,$message) // Windows SMTP requires non-NULL message.
            ,implode($eol,$headers) // Linux sendmail can have message in headers.
        );
    }
    
    static public function valid_craft_text($file=''){
        if(
            ! is_readable($file)
            || is_dir($file)
            || @filesize($file) > static::$max_craft_size
            || @filesize($file) < 250
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
    
    static private function cfg_to_array($text){ // This was/is the hard part.
        $return = array();
        $stack = array( & $return ); // $stack & $pointer together track depth.
        $pointer = & $stack[count($stack)-1];
        
        // Flatten text.
        $text = preg_replace('/(\S+)\s*\{/i','$1{',$text); // Strip whitespace from headers.
        $text = preg_replace('/\/\/.*(\r?\n)/i', '$1', $text); // Remove comments
        $text = preg_replace('/\x{EF}\x{BB}\x{BF}/i','',$text); // Remove BOM.
        $text = preg_replace('/(\r?\n)\s*(\r?\n)/i','$1',$text); // Remove blank lines.
        $text = preg_replace('/\s*(\r?\n)\s*/i','$1',$text); // == trim().
        
        $lines = explode("\n", $text);
        foreach($lines as $line_key => $line){
            if( ! $line = trim($line) ){ continue; }
            $last_pos = strlen($line)-1;
            if(
                strpos($line,'{') === $last_pos
                || strpos($line,'(') === $last_pos
            ){ // Deeper (open node).
                $node = rtrim($line,'{(');
                $node_count = 0;
                $node_patt = preg_quote($node,'/');
                foreach($pointer as $pointer_key => $pointer_val){
                    if( preg_match('/^'.$node_patt.'#\d+$/',$pointer_key) ){
                        $node_count++;
                    }
                }
                $new_node = $node.'#'.$node_count;
                $pointer[$new_node] = array();
                
                $stack[] = & $pointer[$new_node];
                $pointer = & $stack[count($stack)-1];
            }elseif( $line === '}' || $line === ')' ){ // Shallower (close node).
                array_pop($stack);
                $pointer = & $stack[count($stack)-1];
            }else{ // Property/value pair.
                $prop_val = explode('=',$line,2);
                $property = trim(@$prop_val[0]);
                $value = trim(@$prop_val[1]);
                $property_count = 0;
                $property_patt = preg_quote($property,'/');
                if( ! empty($pointer) ){
                    foreach($pointer as $pointer_key => $pointer_val){
                        if( preg_match('/^'.$property_patt.'#\d+$/',$pointer_key) ){
                            $property_count++;
                        }
                    }
                }
                $new_property = $property.'#'.$property_count;
                $pointer[$new_property] = $value;
            }
        }
        return $return;
    }
    
    static private function array_to_cfg($data, $depth=0){ // Recursive, must remain isolated.
        $return = '';
        $indent = '';$i=0;while($i<$depth){$indent .= "\t";$i++;}
        $eol = PHP_EOL;
        $last_key = '';
        foreach( $data as $key => $val ){
            $key = preg_replace('/^(.*)#\d+$/i', '$1', $key); // # to avoid conflict with _ in part names/ids.
            $return .= $indent.$key;
            if( is_array($val) ){
                $return .= $eol.$indent.'{'.$eol;
                $depth++;
                $return .= static::array_to_cfg($val,$depth--);
                $return .= $indent.'}'.$eol;
            }else{
                $return .= ' = '.$val.$eol;
            }
        }
        return $return;
    }
    
/* END OF CLASS */
}
// Created by Erickson Swift, copyright 2014.
?>