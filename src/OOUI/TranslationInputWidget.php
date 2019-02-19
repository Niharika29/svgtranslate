<?php
declare(strict_types = 1);

namespace App\OOUI;

use OOUI\TextInputWidget;

class TranslationInputWidget extends TextInputWidget
{

    /**
     * The class name of the JavaScript version of this widget
     * @return string
     */
    protected function getJavaScriptClassName(): string
    {
        return 'App.TranslationInputWidget';
    }
}
