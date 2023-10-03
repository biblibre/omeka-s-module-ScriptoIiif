<?php

namespace ScriptoIiif\Form;

use Laminas\Form\Element\Text;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'scriptoiiif_motivation',
            'type' => Text::class,
            'options' => [
                'label' => 'Annotation motivation', // @translate
                'info' => 'For instance: "commenting", "supplementing", "tagging", etc. Default is "supplementing"', // @translate
            ],
        ]);
    }
}
