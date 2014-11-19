<?php
class actions_fdf_report {

    const FDF_HEADER = <<<FDF
%FDF-1.2
1 0 obj<</FDF<< /Fields[
FDF;

    // FDF file footer
    const FDF_FOOTER = <<<FDF
] >> >>
endobj
trailer
<</Root 1 0 R>>
%%EOF
FDF;

    function handle($params){
        
        $app = Dataface_Application::getInstance();
        $app->_conf['nocache'] = 1;
        $query = $app->getQuery();
        print_r($query);
        $table = $query['-table'];
        if ( !isset($query['--pdf-template']) ){
            throw new Exception("--pdf-template is a required field.");
        }
        
        $pdf_template = basename($query['--pdf-template']);
        $fdf_filename = substr($pdf_template, 0, strrpos($pdf_template, '.')).'.fdf';
        
        $template_path = DATAFACE_SITE_PATH.'/fdf_templates/'.$pdf_template;
        $fdf_dir_path = DATAFACE_SITE_PATH.'/templates_c/fdf_templates';
        if ( !file_exists($fdf_dir_path) ){
            mkdir($fdf_dir_path, 0777);
        }
        
        $fdf_path = $fdf_dir_path.'/'.basename($fdf_filename);
        
        $pdftk_path = '/usr/bin/pdftk';
        
        if ( !file_exists($fdf_path) or filemtime($fdf_path) < filemtime($template_path) ){
            exec(escapeshellarg($pdftk_path).' '.escapeshellarg($template_path).' generate_fdf output '.escapeshellarg($fdf_path));
        }
        
        $fdf_contents = file_get_contents($fdf_path);
        
        $fields = array();
        if (preg_match_all('/^\/T \(([^\)]*?)\)$/m', $fdf_contents, $matches)){
           $fields = $matches[1];
        } else {
            
        }
        
        $tableObj = Dataface_Table::loadTable($query['-table']);
        $del = $tableObj->getDelegate();
        $delegate_method_exists = isset($del) and method_exists($del, 'fdf_fill_fields');
        
        import('Dataface/RecordReader.php');
        if ( @$query['--single'] ){
            $reader = array($app->getRecord());
        } else {
            $reader = new Dataface_RecordReader($query, 100, false);
        }
        
        $tmpdir = tempnam("/tmp", $query['-table']);
        unlink($tmpdir);
        mkdir($tmpdir, 0777);
        
        
        $count = 0;
        $last_pdf_path = null;
        foreach ($reader as $record ){
            if ( !$record->checkPermission('view') ){
                return Dataface_PermissionDenied("You don't have permission to view one of the records.");
            }
            
            //echo '['.$record->getTitle().'-'.$record->getId().']';
            
            $vals = array();
            foreach ($fields as $f ){
                $vals[$f] = '';
            }
            $this->fill_field_values($record, $vals, $pdf_template, $del,  $tableObj);
            
            $file_name = basename($this->get_fdf_file_name($record, $pdf_template, $del,  $tableObj));
            if ( !preg_match('/\.fdf$/', $file_name) ){
            //
                $file_name .= '.fdf';
            }
            //echo "File name is ".$file_name;
            $file_path = $tmpdir.'/'.$file_name;

            $output_file_name = substr($file_name,0, strrpos($file_name, '.')).'.pdf';
            $output_file_path = $tmpdir.'/'.$output_file_name;
            $last_pdf_path = $output_file_path;
            
            $this->create_fdf($file_path, $vals);
            exec(escapeshellarg($pdftk_path).' '.escapeshellarg($template_path).' fill_form '.escapeshellarg($file_path).' output '.escapeshellarg($output_file_path).' flatten');
            unlink($file_path);
            $count++;
           
            
        }
        
        if ( $count === 1 ){
            // Output just the single PDF that was generated
            header('Content-type: application/pdf');
            echo file_get_contents($last_pdf_path);
        } else {
            // There were many PDFs generated
            echo "Many pdfs were generated.  The last one at ".$last_pdf_path;
        }
        
        
        
    }
    
    function create_fdf($path, $vals){
        $fields = '';
        
        foreach ($vals as $key=>$value) {
            // Create UTF-16BE string encode as ASCII hex
            // See http://blog.tremily.us/posts/PDF_forms/
            $utf16Value = mb_convert_encoding($value,'UTF-16BE', $encoding);
            // Escape parenthesis
            $utf16Value = strtr($utf16Value, array('(' => '\\(', ')'=>'\\)'));
            $fields .= "<</T($key)/V(".chr(0xFE).chr(0xFF).$utf16Value.")>>\n";
        }
        // Use fwrite, since file_put_contents() messes around with character encoding
        //echo "About to try to open $path";
        $fp = fopen($path, 'w');
        if ( !$fp ){
            throw new Exception("Failed to create file $path");
        }
        fwrite($fp, self::FDF_HEADER);
        fwrite($fp, $fields);
        fwrite($fp, self::FDF_FOOTER);
        fclose($fp);
        //echo 'fields: '.$fields;exit;
    }
    
    function fill_field_values(Dataface_Record $record, &$vals, $template_name, $del, $tableObj){
        foreach ( array_keys($vals) as $key ){
            if ( $tableObj->hasField($key) ){
                $vals[$key] = $record->display($key);
            }
        }
        if ( isset($del) and method_exists($del, 'fdf_fill_fields')  ){
            $del->fdf_fill_fields($record, $vals, $template_name);
        } 
    }
        
    function get_fdf_file_name(Dataface_Record $record, $template_name, $del,  $tableObj){
        //echo 'del is '.get_class($del);exit;
        if ( isset($del) and method_exists($del, 'fdf_file_name') ){
            return $del->fdf_file_name($record, $template_name);
        } else {
            $title = $record->getTitle();
            if ( !$title ){
                $title = $record->getId();
            }
            return preg_replace('/[^a-z0-9A-Z]/','_', $title).'.fdf';
        }
    }
    
    function field__sin(Dataface_Record $rec){
        return 'foobar';
    }
}