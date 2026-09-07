<?php
$files = glob('d:/JSTACK CLIENTES/PROYECTO TRIBIO COMIENZO/tribio_final/resources/views/clientes_custom/tflparts/*.blade.php'); 
foreach($files as $f){ 
    if(basename($f) == 'header.blade.php' || basename($f) == 'index.blade.php') continue; 
    $c = file_get_contents($f); 
    $c = str_replace('<!-- Dynamic Header will be rendered in the section loop -->', "@include('clientes_custom.tflparts.header')", $c); 
    file_put_contents($f, $c); 
}
echo "Done";
