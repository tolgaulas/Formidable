<?php

namespace Gregwar\DSD\Fields;

/**
 * Classe parente des champs
 *
 * @author Grégoire Passault <g.passault@gmail.com>
 */
abstract class Field
{
    /**
     * Type du champ (à placer dans le type="")
     */
    protected $type = 'text';

    /**
     * Nom du champ
     */
    protected $name;

    /**
     * Code HTML supplémentaire
     */
    protected $attributes = array();

    /**
     * Valeur du champ
     */
    protected $value = false;

    /**
     * Le champ est t-il optionnel ?
     */
    protected $optional = false;

    /**
     * Expression régulière à respecter
     */
    protected $regex;

    /**
     * Dimensions à respecter
     */
    protected $minlength;
    protected $maxlength;

    /**
     * Nom "joli" (pour les messages d'erreur)
     */
    protected $prettyname;

    /**
     * Lecture seule ?
     */
    protected $readonly = false;

    /**
     * La valeur a t-elle changé ?
     */
    protected $valuechanged = false;

    /**
     * Contraintes
     */
    protected $constraints = array();

    /**
     * Plusieurs valeurs ?
     */
    protected $multiple = false;
    protected $multipleChange = '';

    /**
     * Donnée de mapping pour l'entité
     */
    protected $mapping;

    /**
     * Définir un attribut
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Obtenir un attribut
     */
    public function getAttribute($name)
    {
        if ($this->hasAttribute($name)) {
            return $this->attributes[$name];
        } else {
            return null;
        }
    }

    /**
     * A t-il l'attribut $name ?
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Enlever l'attribut
     */
    public function unsetAttribute($name)
    {
        unset($this->attributes[$name]);
    }

    /**
     * Fonction apellée par le dispatcher
     */
    public function push($name, $value = null)
    {
        switch ($name) {
        case 'required':
            break;
        case 'name':
            $this->name = $value;
            break;
        case 'type':
            break;
        case 'value':
            $this->setValue($value, true);
            break;
        case 'optional':
            $this->optional = true;
            break;
        case 'regex':
            $this->regex = $value;
            break;
        case 'minlength':
            $this->minlength = $value;
            break;
        case 'maxlength':
            $this->maxlength = $value;
            $this->attributes['maxlength'] = $value;
            break;
        case 'multiple':
            $this->multiple = true;
            break;
        case 'multiplechange':
            $this->multipleChange = $value;
            break;
        case 'mapping':
            $this->mapping = $value;
            break;
        case 'prettyname':
            $this->prettyname = $value;
            break;
        case 'readonly':
            $this->readonly = true;
            $this->attributes['readonly'] = 'readonly';
            break;
        default:
            if (preg_match('#^([a-z0-9_-]+)$#mUsi', $name)) {
                if (null !== $value) {
                    $this->setAttribute($name, $value);
                } else {
                    $this->setAttribute($name, $name);
                }
            }
        }
    }

    public function printName()
    {
        if ($this->prettyname)

            return $this->prettyname;
        return $this->name;
    }

    /**
     * Test des contraintes
     */
    public function check()
    {
        if ($this->valuechanged && $this->readonly) {
            return 'Le champ '.$this->printName().' est en lecture seule et ne doit pas changer';
        }

	if ($this->multiple && is_array($this->value)) {
            $nodata = implode('', $this->value) === '';

            if (!$this->optional && $nodata) {
                return 'Vous devez saisir une valeur pour '.$this->printName();
            }

            // Répectution du test sur chaque partie
            $values = $this->value;
            foreach ($values as $value) {
                $this->value = $value;
                $error = $this->check();
                if ($error) {
                    $this->value = $values;
                    return $error;
                }
            }
            $this->value = $values;

            return;
        }

        if (null === $this->value || '' === $this->value) {
            if (!$this->optional) {
                return 'Vous devez saisir une valeur pour '.$this->printName();
            }
        } else {
            // Expressions régulière
            if ($this->regex) {
                if (!preg_match('/'.$this->regex.'/mUsi', $this->value)) {
                    return 'Le format du champ '.$this->printName().' est incorrect';
                }
            }

            // Longueur minimum et maximum
            if ($this->minlength && strlen($this->value) < $this->minlength) {
                return 'Le champ '.$this->printName().' doit faire au moins '.$this->minlength.' caracteres.';
            }

            if ($this->maxlength && strlen($this->value) > $this->maxlength) {
                return 'Le champ '.$this->printName().' ne doit pas dépasser '.$this->maxlength.' caracteres.';
            }
        }

        // Contraintes custom
        foreach ($this->constraints as $constraint) {
            $err = $constraint($this->value);
            if ($err) {
                return $err;
            }
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getMappingName()
    {
        return $this->mapping;
    }

    public function getValue()
    {
        return $this->value;
    }

    /**
     * Définition de la valeur
     */
    public function setValue($value, $default = false)
    {
        if ($value != $this->value && !$default) {
            $this->valuechanged = true;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            $this->value = (string)$value;
        } else {
            $this->value = null;
        }

	if ($this->multiple) {
            $this->value = null;
            if (is_array($value)) {
                foreach ($value as $val) {
                    if (!is_string($val)) {
                        return;
                    }
                }
		$this->value = $value;
            }
        }
    }

    public function getHtmlForValue($given_value = '', $name_suffix = '')
    {
        $html = '<input ';
        foreach ($this->attributes as $name => $value) {
            $html.= $name.'="'.$value.'" ';
        }
        if (!$this->optional) {
            $html.= 'required="required" ';
        }
        $html.= 'type="'.$this->type.'" ';
        $html.= 'name="'.$this->name.$name_suffix.'" ';
        if ($given_value) {
            $html.= 'value="'.htmlspecialchars($given_value).'" ';
        }
        $html.= '/>';

        return $html;
    }

    public function getHtml()
    {
        if (!$this->multiple) {
            return $this->getHtmlForValue($this->value);
	} else {
            $rnd = sha1(mt_rand().time().mt_rand());

	    if (!is_array($this->value) || !$this->value) {
                $this->value = array('');
            }

            $others = '';
	    if ($this->multiple && is_array($this->value)) {
                foreach ($this->value as $id => $value) {
                    $others.="DSD.addInput(\"$rnd\",\"";
                    $others.=str_replace(
                        array("\r", "\n"), array('', ''),
                        addslashes($this->getHtmlForValue($value, '[]'))
                    );
                    $others.="\");\n";
                }
            }

            $prototype = $this->getHtmlForValue('', '[]');

            $html= '<span id="'.$rnd.'"></span>';
            $html.= '<script type="text/javascript">'.$others.'</script>';
            $html.= "<a href=\"javascript:DSD.addInput('$rnd','".str_replace(array("\r","\n"),array("",""),htmlspecialchars($prototype))."');".$this->multipleChange."\">Ajouter</a>";

            return $html;
        }
    }

    public function getSource()
    {
        return '';
    }

    public function source($values)
    {
    }

    public function needJs()
    {
        return $this->multiple;
    }

    public function addConstraint($closure)
    {
        if (!$closure instanceof \Closure) {
            throw new \InvalidArgumentException('L\'argument de addConstraint() doit être une \Closure');
        }

        $this->constraints[] = $closure;
    }

    public function readOnly()
    {
        return $this->readonly;
    }
}
