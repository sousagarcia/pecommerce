<?php
/**
 * MultiField Widget: Takes a group of input fields and gives them the possibility to register many occurrences
 *
 * @version    1.0
 * @package    widget_web
 * @subpackage form
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006-2014 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TMultiField extends TField implements IWidget
{
    private $fields;
    private $objects;
    private $height;
    private $width;
    private $className;
    private $orientation;
    protected $formName;
    
    /**
     * Class Constructor
     * @param $name Name of the widget
     */
    public function __construct($name)
    {
        // define some default properties
        self::setEditable(TRUE);
        self::setName($name);
        $this->orientation = 'vertical';
        $this->fields = array();
        $this->height = 100;
    }

    /**
     * Define form orientation
     * @param $orientation (vertical, horizontal)
     */
    public function setOrientation($orientation)
    {
        $this->orientation = $orientation;
    }
    
    /**
     * Define the name of the form to wich the multifield is attached
     * @param $name    A string containing the name of the form
     * @ignore-autocomplete on
     */
    public function setFormName($name)
    {
        parent::setFormName($name);
        
        if ($this->fields)
        {
            foreach($this->fields as $name => $field)
            {
                $obj = $field->{'field'};
                $obj->setFormName($this->formName);
            }
        }
    }
    
    /**
     * Add a field to the MultiField
     * @param $name      Widget's name
     * @param $text      Widget's label
     * @param $object    Widget
     * @param $size      Widget's size
     * @param $mandatory Mandatory field
     */
    public function addField($name, $text, TField $object, $size, $mandatory = FALSE)
    {
        $obj = new StdClass;
        $obj-> name      = $name;
        $obj-> text      = $text;
        $obj-> field     = $object;
        $obj-> size      = $size;
        $obj-> mandatory = (int) $mandatory;
        $this->width   += $size;
        $this->fields[$name] = $obj;
        
        if ($object instanceof TComboCombined)
        {
            $this->width += 20;
        }
    }
    
    /**
     * Define the class for the Active Records returned by this component
     * @param $class Class Name
     */
    public function setClass($class)
    {
        $this->className = $class;
    }
    
    /**
     * Returns the class defined by the setClass() method
     * @return the class for the Active Records returned by this component
     */
    public function getClass()
    {
        return $this->className;
    }
    
    /**
     * Define the MultiField content
     * @param $objects A Collection of Active Records
     */
    public function setValue($objects)
    {
        $this->objects = $objects;
        
        // This block is executed just to call the
        // getters like get_virtual_property()
        // inside the transaction (when the attribute)
        // is set, and not after all (during the show())
        if ($objects)
        {
            foreach ($this->objects as $object)
            {
                if ($this->fields)
                {
                    foreach($this->fields as $name => $obj)
                    {
                        $object->$name; // regular attribute
                        if ($obj-> field instanceof TComboCombined)
                        {
                            $attribute = $obj-> field->getTextName();
                            $object->$attribute; // auxiliar attribute
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Return the post data
     */
    public function getPostData()
    {
        if (isset($_POST[$this->name]))
        {
            $val = $_POST[$this->name];
            $className = $this->getClass() ? $this->getClass() : 'stdClass';
            $decoded   = JSON_decode(stripslashes($val));
            unset($items);
            unset($obj_item);
            $items = array();
            if (is_array($decoded))
            {
                foreach ($decoded as $std_object)
                {
                    $obj_item = new $className;
                    foreach ($std_object as $subkey => $value)
                    {
                        // substitui pq o ttable gera com quebra de linha no multifield
                        $obj_item->$subkey = utf8_encode(str_replace("\n",'',URLdecode($value)));
                    }
                    $items[] = $obj_item;
                }
            }
            return $items;
        }
        else
        {
            return '';
        }
    }
    
    /**
     * Define the MultiField height
     * @param $height Height in pixels
     */
    public function setHeight($height)
    {
        $this->height = $height;
    }
    
    /**
     * Enable the field
     * @param $form_name Form name
     * @param $field Field name
     */
    public static function enableField($form_name, $field)
    {
        $script = new TElement('script');
        $script->{'language'} = 'JavaScript';
        $script->setUseSingleQuotes(TRUE);
        $script->setUseLineBreaks(FALSE);
        $script->add("setTimeout(function() {\$('div[mtf_name=\"block_{$field}\"]').remove();}, 20);");
        $script->show();
    }
    
    /**
     * Disable the field
     * @param $form_name Form name
     * @param $field Field name
     */
    public static function disableField($form_name, $field)
    {
        $script = new TElement('script');
        $script->{'language'} = 'JavaScript';
        $script->setUseSingleQuotes(TRUE);
        $script->setUseLineBreaks(FALSE);
        $script->add("setTimeout(function() {\$('div[mtf_name=\"block_{$field}\"]').remove();}, 19);");
        $script->add("setTimeout(function() {\$('div[mtf_name=\"{$field}\"]').css('position', 'relative').prepend('<div mtf_name=\"block_{$field}\" style=\"position:absolute; width:'+\$('div[mtf_name=\"{$field}\"]').width()+'px; height:'+$('div[mtf_name=\"{$field}\"]').height()+'px; background: #c0c0c0; opacity:0.5;\"></div>')}, 20);");
        $script->show();
    }
    
    /**
     * Clear the field
     * @param $form_name Form name
     * @param $field Field name
     */
    public static function clearField($form_name, $field)
    {
        $script = new TElement('script');
        $script->{'language'} = 'JavaScript';
        $script->setUseSingleQuotes(TRUE);
        $script->setUseLineBreaks(FALSE);
        $script->add("\$('#{$field}mfTable .tmultifield_scrolling').html('');");
        $script->show();
    }
    
    /**
     * Show the widget at the screen
     */
    public function show()
    {
        $wrapper = new TElement('div');
        $wrapper->{'mtf_name'} = $this->getName();
        // include the needed libraries and styles
        if ($this->fields)
        {
            $table = new TTable;
            
            $mandatory = array(); // mandatory
            $fields = array();
            $i=0;
            
            if ($this->orientation == 'horizontal')
            {
                $row_label = $table->addRow();
                $row_field = $table->addRow();
            }
            
            foreach($this->fields as $name => $obj)
            {
                if ($this->orientation == 'vertical')
                {
                    $row = $table->addRow();
                    $row_label = $row;
                    $row_field = $row;
                }
                
                $label = new TLabel($obj-> text);
                if ($obj-> mandatory)
                {
                    $label->setFontColor('red');
                }
                
                $row_label->addCell($label);
                $row_field->addCell($obj-> field);
                
                $mandatory[] = $obj->mandatory;
                $fields[] = $name;
                $post_fields[$name] = 1;
                $sizes[$name] = $obj-> size;
                
                $obj-> field->setName($this->name.'_'.$name);
                if (get_class($obj-> field) == 'TComboCombined')
                {
                    $aux_name = $obj-> field->getTextName();
                    $aux_full_name = $this->name.'_'.$aux_name;
                    
                    $mandatory[] = 0;
                    $obj-> field->setTextName($aux_full_name);
                    
                    $fields[] = $aux_name;
                    $post_fields[$aux_name] = 1;
                    
                    // invert sizes
                    $sizes[$aux_name] = $obj-> size;
                    $sizes[$name] = 20;
                    $i++;
                }
                $i++;
            }
            $wrapper->add($table);
        }
        // check whether the widget is non-editable
        if (parent::getEditable())
        {
            // create three buttons to control the MultiField
            $add = new TButton("{$this->name}btnStore");
            $add->setLabel(TAdiantiCoreTranslator::translate('Register'));
            //$add->setName("{$this->name}btnStore");
            $add->setImage('ico_save.png');
            $add->addFunction("mtf{$this->name}.addRowFromFormFields()");
            
            $del = new TButton("{$this->name}btnDelete");
            $del->setLabel(TAdiantiCoreTranslator::translate('Delete'));
            $del->setImage('ico_delete.png');
            
            $can = new TButton("{$this->name}btnCancel");
            $can->setLabel(TAdiantiCoreTranslator::translate('Cancel'));
            $can->setImage('ico_close.png');
            
            $table = new TTable;
            $row=$table->addRow();
            $row->addCell($add);
            $row->addCell($del);
            $row->addCell($can);
            $wrapper->add($table);
        }
        
        // create the MultiField Panel
        $panel = new TElement('div');
        $panel->{'class'} = "multifieldDiv";
        
        $input = new THidden($this->name);
        $panel->add($input);
        
        // create the MultiField DataGrid Header
        $table = new TTable;
        $table-> id="{$this->name}mfTable";
        $head = new TElement('thead');
        $table->add($head);
        $row = new TTableRow;
        $head->add($row);
        
        // fill the MultiField DataGrid
        if ($this->fields)
        {
            foreach ($this->fields as $obj)
            {
                $c = $obj-> text;
                if (get_class($obj-> field) == 'TComboCombined')
                {
                    $cell=$row->addCell('ID');
                    $cell-> width= '20px';
                }
                $cell = $row->addCell($c);
                $cell-> width=$obj-> size.'px';
            }
        }
        $body_height = $this->height - 27;
        $body = new TElement('tbody');
        $body-> style="height: {$body_height}px";
        $table->add($body);
        
        if ($this->objects)
        {
            foreach($this->objects as $obj)
            {
                if (isset($obj-> id))
                {
                    $row = new TTableRow;
                    $row-> dbId=$obj-> id;
                    $body->add($row);
                }
                else
                {
                    $row = new TTableRow;
                    $body->add($row);
                }
                if ($fields)
                {
                    foreach($fields as $name)
                    {
                        $cell = $row->addCell(is_null($obj->$name) ? '' : $obj->$name);
                        if (isset($sizes[$name]))
                        {
                            $cell-> style='width:'.$sizes[$name].'px';
                        }
                    }
                }
            }
        }
        $panel->add($table);
        $wrapper->add($panel);
        $wrapper->show();
        
        echo '<script type="text/javascript">';
        echo "var mtf{$this->name};";
        //echo '$(document).ready(function() {';
        echo "mtf{$this->name} = new MultiField('{$this->name}mfTable',{$this->width},{$this->height});\n";
        $s = implode("','",$fields);
        echo "mtf{$this->name}.formFieldsAlias = Array('{$s}');\n";
        $sfields = implode("','{$this->name}_",$fields);
        echo "mtf{$this->name}.formFieldsName = Array('{$this->name}_{$sfields}');\n";
        echo "mtf{$this->name}.formPostFields = Array();\n";
        if ($post_fields)
        {
            foreach ($post_fields as $col =>$value)
            {
                echo "mtf{$this->name}.formPostFields['{$col}'] = '$value';\n";
            }
        }
            
        $mdr_array = implode(',', $mandatory);
        echo "mtf{$this->name}.formFieldsMandatory = [{$mdr_array}];\n";
        echo "mtf{$this->name}.mandatoryMessage = '".TAdiantiCoreTranslator::translate('The field ^1 is required')."';\n";
        echo "mtf{$this->name}.storeButton  = document.getElementsByName('{$this->name}btnStore')[0];\n";
        echo "mtf{$this->name}.deleteButton = document.getElementsByName('{$this->name}btnDelete')[0];\n";
        echo "mtf{$this->name}.cancelButton = document.getElementsByName('{$this->name}btnCancel')[0];\n";
        echo "mtf{$this->name}.inputResult  = document.getElementsByName('{$this->name}')[0];\n";
        //echo '});';
        echo '</script>';
    }
}
?>