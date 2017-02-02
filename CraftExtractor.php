<?php
class CraftExtractor{
    
    static
        $cfg_node
        , $save_array
        , $title
        , $version
        , $mode
        , $db
        , $gank_dir = '_CraftExtractor_ganks'
        , $zip_file_prefix = 'Crafts-from-'
        , $max_file_size = 10485760 // bytes. 10485760 = 10MB, 5242880 = 5MB
        , $output
    ;
    
    function __construct(){
        
        @set_time_limit(@ini_get('max_execution_time')+120);
        
        function human_filesize($bytes, $decimals = 2) {
            $sz = array('Bytes','kB','MB','GB','TB','PB');
            $factor = floor((strlen($bytes) - 1) / 3);
            return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
        }
        if(
            empty($_FILES['craft_extractor']['tmp_name'])
            OR ! is_uploaded_file($_FILES['craft_extractor']['tmp_name'])
        ){
            if( ! empty($_FILES['craft_extractor']['error']) ){
                switch($_FILES['craft_extractor']['error']){
                    case UPLOAD_ERR_INI_SIZE:
                        $this->my_die('File is too large for the server. Limit: '.human_filesize(ini_get('upload_max_filesize')).'.  Please tell the admin.',$_FILES);
                    break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $this->my_die('File is too large for the form. Limit: '.human_filesize(static::$max_file_size).'.',$_FILES);
                    break;
                    case UPLOAD_ERR_PARTIAL:
                        $this->my_die('The file was only partially uploaded.  Please try again.');
                    break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                    case UPLOAD_ERR_CANT_WRITE:
                    case UPLOAD_ERR_EXTENSION:
                        $this->my_die('Server error.  Please tell the admin.');
                    break;
                }
            }
            static::$output = '
<form name="craft_extractor" method="post" enctype="multipart/form-data"><fieldset>
    <br/>
    <label for="craft_extractor">Click to browse, or drag and drop your <strong><em>.sfs</em></strong> file here.</label><br/>
    <input type="hidden" name="MAX_FILE_SIZE" value="'.static::$max_file_size.'" />
    <input type="file" name="craft_extractor" id="craft_extractor" /><br/>
    <br/>
    <input type="submit" value="Extract" />
</fieldset></form>';
            return;
        }
        
        $save_file = $_FILES['craft_extractor'];
        $file_ext = pathinfo($save_file['name'],PATHINFO_EXTENSION);
        $file_type = @finfo_file(@finfo_open(FILEINFO_MIME_TYPE), $save_file['tmp_name']);
        $file_size = filesize($save_file['tmp_name']);
        
        $file_notes = array($save_file);
        
        if( $file_ext != 'sfs'){ $this->my_die('File is not a KSP save.',$file_notes); }
        if( $file_type != 'text/plain' ){ $this->my_die('File is not plain text.',$file_notes); }
        if( $file_size > static::$max_file_size ){
            $this->my_die('File is too large. Limit: '.human_filesize(static::$max_file_size).' bytes.',$file_notes);
        }
        
        if( empty(static::$cfg_node) ){
            include_once('class/CfgNode.php');
            static::$cfg_node = new CfgNode;
            static::$save_array = static::$cfg_node->cfg_to_array(file_get_contents($save_file['tmp_name']));
        }
        
        // Extract vessels from save file.
        $vessels = array();
        foreach( static::$save_array as $game ){ // Only 1 game per file?
            static::$version = @$game['version#0'];
            static::$title = @$game['Title#0'];
            static::$mode = @$game['Mode#0'];
            foreach( $game as $game_key => $game_val ){
                if( preg_match('/^FLIGHTSTATE#\d+$/',$game_key) ){
                    foreach( $game_val as $key => $val ){
                        if(
                            preg_match('/^VESSEL#\d+$/',$key)
                            AND ! preg_match('/^Ast\./',$val['name#0']) // Skip asteroids.
                        ){
                            $vessels[] = $val;
                        }
                    }
                }
            }
            break; // Only 1 game per file?
        }
        
        if( empty($vessels) ){
            $this->my_die('No in-flight vessels were found.',$file_notes);
        }
        
        // Transform to .craft format.
        
        $compound_parts = array('strutConnector','fuelLine');
        $strip_props = array('cid','uid','mid','launchID','parent','position','rotation','mirror','mass','temp','tempExt','expt','state','connected','attached','flag','rTrf','crew');
        foreach( $strip_props as $key => $prop ){
            $strip_props[$key] .= '#0';
        }
        $new_craft = array(
            'ship' => '',
            'version' => static::$version,
            'description' => 'Extracted from save: '.static::$title,
            'type' => 'VAB', // Any way to get from save file?
            'size' => ''
        );
        $new_part = array('part','partName','pos','attPos','attPos0','rot','attRot','attRot0','mir','symMethod','istg','dstg','sidx','sqor','sepI','attm','modCost','modMass','modSize');
        $new_part_temp = array();
        foreach( $new_part as $key => $val ){
            $new_part_temp[$val.'#0'] = '';
        }
        $new_part = $new_part_temp;
        $simple_props = array('name','cid','part','partName','pos','rot','mir');
        foreach( $simple_props as $key => $val ){
            $simple_props[$key] .= '#0';
        }
        
        // Setup DB & get/organize data just before needed.
/*  NOT NEEDED (?)
        if( empty(static::$db) ){
            include_once('class/Database.php');
			$db = new Database('_sqlite/KSP-GameData.sqlite3');
			if( $db_err = $db->get_error() ){
				die($class.' : DB failure: '.$db_err);
			}
            $db_err = $db->get_error();
            if( ! empty($db_err) ){
				die($class.' : DB failure: '.$db_err);
			}            
			static::$db = $db;
        }
        $db_parts = static::$db->read('parts');
        $parts_defaults = array();
        foreach( $db_parts as $part ){
            $part_data = static::$cfg_node->cfg_to_array($part['file_text']);
            $part_defaults = array();
            foreach( $part_data['PART#0'] as $part_key => $node ){
                if( is_array($node) ){
                    $part_defaults[$part_key] = $node;
                }
            }
            $parts_defaults[$part['folder']] = $part_defaults;
        }
*/
        
        $crafts = array();
        foreach( $vessels as $vessel ){
            
            // Collect parts.
            $parts = array();
            foreach( $vessel as $vessel_key => $vessel_val ){
                if( ! preg_match('/^PART#\d+$/',$vessel_key) ){continue;}
                if( ! is_array($vessel_val) ){continue;}
                $part = $vessel_val;
                
                $part_temp = $new_part;
                
                // Transpose simple props.
                $part_temp['part#0'] = $part['name#0'].'_'.$part['cid#0'];
                if( in_array($part['name#0'],$compound_parts) ){
                    $part_temp['partName#0'] = 'CompoundPart';
                }else{
                    $part_temp['partName#0'] = 'Part';
                }
                $part_temp['attPos#0'] = '0,0,0'; // Not used?
                $part_temp['rot#0'] = $part['rotation#0'];
                $part_temp['attRot#0'] = $part['rotation#0'];
                $part_temp['attRot0#0'] = $part['rotation#0'];
                $part_temp['mir#0'] = $part['mirror#0'];
                $part_temp['modMass#0'] = '0';
                $part_temp['modSize#0'] = '(0.0, 0.0, 0.0)';
                
                // Copy the rest for now.
                foreach( $part as $part_key => $part_val ){
                    // Skip simple props already defined above.
                    if( in_array($part_key,$simple_props) ){continue;}
                    
                    $part_temp[$part_key] = $part_val;
                }
                
                $parts[] = $part_temp;
            }
            
            $part_count = count($parts);
            
            // Convert part tree; parent to link, part index to name_cid.
            foreach( $parts as $parts_key => $part ){
                
                $parent = $part['parent#0'];
                
                if( $parts_key == 0 ){ // Root parts only.
                    // Set position of root part just above height of ship.
                    if(
                        empty($vessel['hgt#0'])
                        OR $vessel['hgt#0'] == '-1'
                    ){
                        $root_pos = array(0,25,0);
                    }else{
                        $root_pos = array(0, 15+( 0.5 * round($vessel['hgt#0']) ) ,0);
                    }
                    $part['position#0'] = implode(',',$root_pos);
                    $part['pos#0'] = $part['position#0'];
                    $part['attPos0#0'] = $part['pos#0'];
                }else{ // NON-root parts only.
                    
                    // Figure position.
                    $attPos0 = explode(',',$part['position#0']);
                    $pos = array();
                    foreach( $attPos0 as $key => $val ){
                        $attPos0[$key] = round($val,7);
                        $pos[$key] = $root_pos[$key] + $attPos0[$key];
                        $pos[$key] = round($pos[$key],7);
                    }
                    $part['pos#0'] = implode(',',$pos);
                    $part['attPos0#0'] = implode(',',$attPos0);
                    
                    
                    // Create link in parent.
                    $link_count = 0;
                    $link_exists = false;
                    while( ! empty($parts[$parent]['link#'.$link_count]) ){
                        if( $parts[$parent]['link#'.$link_count] == $part['part#0'] ){
                            $link_exists = true;
                        }
                        $link_count++;
                    }
                    if( ! $link_exists ){
                        $parts[$parent]['link#'.$link_count++] = $part['part#0'];
                    }
                    
                }
                
                // All parts.
                foreach( $part as $part_key => $part_val ){
                    
                    if( is_array($part_val) ){
                        continue;
                    }
                    
                    if(
                        ! empty($parts[$part_val]['part#0'])
                        AND preg_match('/^sym#\d+$/',$part_key)
                    ){
                        $part[$part_key] = $parts[$part_val]['part#0'];
                    }
                    
                    $csv = explode(',',$part_val);
                    foreach($csv as $csv_key => $csv_val){
                        $csv[$csv_key] = trim($csv_val);
                    }
                    if(
                        count($csv) == 2
                        AND( // Find attached parts.
                            preg_match('/^attN#\d+$/',$part_key)
                            OR preg_match('/^srfN#\d+$/',$part_key)
                        )
                    ){
                        if( // These seem to mean "nothing" or NULL...
                            $csv[0] == ''
                            OR $csv[1] == '-1'
                        ){
                            $part[$part_key] = ''; // ... so blank them.
                        }elseif(
                            ! is_numeric( $csv[1] )
                            OR empty($parts[$csv[1]])
                        ){
                            $this->my_die('Parse ERROR: Attached part\'s index not found. Is the save file corrupted?',$file_notes);
                        }else{
                            $part[$part_key] = $csv[0].','.$parts[$csv[1]]['part#0'];
                        }
                    }
                }
                
                // Strip non-craft props.
                $part_temp = $part;
                foreach($part_temp as $part_key => $part_val){
                    if( in_array($part_key,$strip_props) ){
                        unset($part[$part_key]);
                    }
                }
                unset($part_temp);
                
                // Strip empty props.
                $part_temp = $part;
                foreach($part_temp as $part_key => $part_val){
                    if(
                        empty($part_val)
                        AND $part_val != '0'
                        AND $part_val !== false
                    ){
                        unset($part[$part_key]);
                    }
                }
                unset($part_temp);
                
                $parts[$parts_key] = $part;
            }
            
            $new_parts = array();
            foreach( $parts as $parts_key => $parts_val ){
                $new_parts['PART#'.$parts_key] = $parts_val;
            }
            $parts = $new_parts;
            
            // LAST STEP: Flesh out parts with DB data (MODULEs and such).
/* NOT NEEDED (?)
            foreach( $parts as $parts_key => $part ){
                $part_name = preg_replace('/_\d+/','',$parts[$parts_key]['part#0']);
                // Skip unknwon parts (mods).
                if( empty($parts_defaults[$part_name]) ){continue;}
                $parts[$parts_key] = array_merge($part,$parts_defaults[$part_name]);
            }
*/
            
            $craft_temp = $new_craft;
            $craft_temp['ship'] = $vessel['name#0'].'_EXTRACTED';
            $crafts[] = array_merge($craft_temp,$parts);
        }
        
        // Downloads for production, display for dev maybe.
        if( $_SERVER['HTTP_HOST'] === 'www.kerbaltek.com' ){
            $this->download_files($crafts);
        }else{
            $this->download_files($crafts);
//            $this->display_files($crafts);
        }
        
    // END of __construct().
    }
    
    function my_die($message,$debug=null){
        if( empty($debug) ){
            $debug = '';
        }else{
            $debug = "\r\nDebug:\r\n".print_r($debug,true)."\r\n";
        }
        if( ! class_exists('Mailer') ){
            include_once('class/Mailer.php');
        }
        $mailer = new Mailer;
        $mailer->send_message($message.$debug,get_called_class());
        die('<p>'.$message.'</p><p><a title="Return" href="?">Return</a></p>');
    }
    function display_files($crafts){
        header( 'Content-Type: text/plain' );
        foreach( $crafts as $craft){
            $craft = static::$cfg_node->array_to_cfg($craft);
            print_r($craft);
        }
        exit();
    }
    function download_files($crafts){
        $filename = static::$zip_file_prefix.static::$title.'.zip';
        $dir = static::$gank_dir;
        if( ! empty($dir) ){
            if( ! file_exists($dir) ){
                mkdir($dir);
            }
            if(
                ! is_dir($dir)
                OR ! is_writable($dir)
            ){
                die(get_called_class().': Zip dir not a dir or not writable. Please tell the admin.');
            }
            $dir .= '/';
        }
        $pathname = $dir.$filename;
        
        $zip_obj = new ZipArchive();
        $zip_success = $zip_obj->open($pathname, ZIPARCHIVE::OVERWRITE);
        if( $zip_success !== true ){
            die(get_called_class().': Zip open error. Please tell the admin.');
        }
        foreach( $crafts as $craft ){
            $craft_filename = $craft['ship'].'.craft';
            $craft_text = static::$cfg_node->array_to_cfg($craft);
            $zip_success = $zip_obj->addFromString($craft_filename,$craft_text);
            if( $zip_success !== true ){
                die(get_called_class().': Zip add error. Please tell the admin.');
            }
        }
        $zip_success = $zip_obj->close();
        if( $zip_success !== true ){
            die(get_called_class().': Zip close error. Please tell the admin.');
        }
        
        if( ! class_exists('Mailer') ){
            include_once('class/Mailer.php');
        }
        $mailer = new Mailer;
        $mailer->send_message('A new zip was generated at: '.$pathname,get_called_class());
        
        $filename = preg_replace('/;/i', '_',$filename);
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Disposition: attachment; filename="'.$filename.'"' );
        header( 'Content-Transfer-Encoding: binary' );
        readfile($pathname);
        exit();
    }
}
?>