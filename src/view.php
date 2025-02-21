<?php

namespace Dasintranet\Framework;

use Dasintranet\Framework\App;

class View extends App{
    //====================== View =============================================
    public function render($viewFile, $data = []){
        //Expand data to output
        extract($data);

        //Replace . on the route for /
        $viewFile = str_replace('.', '/', $viewFile);

        //Verify file exist
        if(file_exists(App::getViews()."/{$viewFile}.php")){
            //Active capture output
            ob_start();
            include App::getViews()."/{$viewFile}.php";
            $content = ob_get_clean();
            return $content;
        }else{
            return "Not found view file: ".App::getViews()."/$viewFile.php";
        }
    }

    public function extend($viewFile, $data = []){
        //Expand data to output
        extract($data);

        //Replace . on the route for /
        $viewFile = str_replace('.', '/', $viewFile);

        //Verify file exist
        if(file_exists(App::getViews()."/{$viewFile}.php")){
            //Active capture output
            ob_start();
            include App::getViews()."/App/Views/layouts/appHead.php";
            include App::getViews()."/App/Views/{$viewFile}.php";
            include App::getViews()."/App/Views/layouts/appFooter.php";
            $content = ob_get_clean();
            return $content;
        }else{
            return "Not found view file: ".App::getViews()."/$viewFile.php";
        }
    }
}