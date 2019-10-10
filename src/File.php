<?php
declare(strict_types=1);

/*
 * This file is part of the QuidPHP package.
 * Website: https://quidphp.com
 * License: https://github.com/quidphp/main/blob/master/LICENSE
 */

namespace Quid\Main;
use Quid\Base;

// file
// class for a basic file object
class File extends Res
{
    // trait
    use _option;


    // config
    public static $config = [
        'group'=>null, // groupe par défaut, par défaut rien, si tu mets false la classe getClass ne cherchera pas de classe
        'mime'=>null, // définit le mime par défaut à utiliser (par exemple lors de la création d'une ressource temporaire)
        'option'=>[
            'create'=>false, // crée le fichier si non existant
            'read'=>null, // option pour read
            'write'=>null], // option pour write
    ];


    // param
    public static $param = [
        'storageClass'=>[], // défini les classes storages, un dirname dans celui défini de la classe doit utilisé un objet particulier
        'utilClass'=>[], // défini les classes utilités
        'groupClass'=>[] // défini la classe à utiliser selon le mimeGroup du fichier
    ];


    // construct
    // construit l'objet fichier
    public function __construct($value,?array $option=null)
    {
        $option = $this->prepareOption($value,$option);
        $this->option($option);
        parent::__construct($value,$option);

        return;
    }


    // isResourceValid
    // méthode qui détermine si la resource est courante valide pour la classe
    public function isResourceValid():bool
    {
        $return = false;
        $mimeGroup = static::defaultMimeGroup();
        $resource = $this->resource();

        if(is_string($mimeGroup))
        $return = (Base\Res::isEmpty($resource) || (Base\Res::isMimeGroup($group,$resource)))? true:false;

        else
        $return = (Base\Res::isEmpty($resource) || Base\Res::isFileLike($resource))? true:false;

        return $return;
    }


    // checkResourceValid
    // envoie une exception si la resource courante n'est pas valide pour la classe
    public function checkResourceValid():self
    {
        if(!$this->isResourceValid())
        static::throw();

        return $this;
    }


    // prepareOption
    // essaie d'attribuer un mime à utiliser si non défini
    // retourne le tableau option
    protected function prepareOption($value,?array $option=null):array
    {
        $return = (array) $option;

        if(empty($option['mime']))
        {
            if(!Base\File::is($value) && !Base\Res::is($value))
            {
                $mime = null;

                if(is_string($value))
                $mime = Base\Path::mime($value);

                if(empty($mime))
                $mime = static::defaultExtension();

                if(is_string($mime))
                $return['mime'] = $mime;
            }
        }

        return $return;
    }


    // readOption
    // retourne les options à utiliser lors du read
    public function readOption():?array
    {
        return $this->getOption('read');
    }


    // writeOption
    // retourne les options à utiliser pour écrire dans l'objet
    public function writeOption():?array
    {
        return $this->getOption('write');
    }


    // unlinkOnShutdown
    // permet de unlink le fichier au shutdown, s'il existe toujours
    public function unlinkOnShutdown():void
    {
        Base\File::unlinkOnShutdown($this->path());

        return;
    }


    // makeUploadArray
    // retourne un upload array à partir d'un fichier
    // envoie une exception si le retour est null
    public function makeUploadArray(int $error=0,bool $is=true):array
    {
        $return = Base\File::makeUploadArray($this,$error,$is);

        if(!is_array($return))
        static::throw();

        return $return;
    }


    // files
    // créer un nouvel objet files et met le fichier courant dedans
    public function files(...$args):Files
    {
        $return = static::newFiles(...$args);
        $return->add($this);

        return $return;
    }


    // defaultMimeGroup
    // retourne le mime groupe par défaut pour la classe
    public static function defaultMimeGroup():?string
    {
        $return = static::$config['group'] ?? null;
        $return = (empty($return))? null:$return;

        return $return;
    }


    // defaultExtension
    // retourne l'extension par défaut selon le mime groupe de la classe
    public static function defaultExtension():?string
    {
        $return = null;
        $group = static::defaultMimeGroup();

        if(!empty($group))
        $return = Base\Mime::extensionFromGroup($group);

        return $return;
    }


    // getClass
    // retourne la classe à utiliser avec la méthode new
    public static function getClass($value,?array $option=null):string
    {
        $return = static::class;

        if(!in_array($return,static::$param['storageClass'],true) && !in_array($return,static::$param['groupClass'],true) && !in_array($return,static::$param['utilClass'],true))
        {
            $dirname = static::getDirnameFromValue($value);
            $storage = null;
            if(!empty($dirname))
            $storage = static::getClassFromDirname($dirname);

            if(!empty($storage))
            $return = $storage;

            else
            {
                $group = Base\Mime::getGroup($value);

                if(empty($group) && !empty($option['mime']))
                $group = Base\Mime::group($option['mime']);

                if(empty($group) && !empty($option['basename']) && is_string($option['basename']))
                $group = Base\Mime::groupFromBasename($option['basename']);

                if(empty($group))
                $group = static::defaultMimeGroup();

                if(is_string($group))
                $return = static::getClassFromGroup($group) ?? static::class;
            }
        }

        if(!empty($return))
        $return = $return::getOverloadClass();

        return $return;
    }


    // getClassFromGroup
    // retourne la classe à utiliser à partir du groupe
    public static function getClassFromGroup(string $group):?string
    {
        return (array_key_exists($group,static::$param['groupClass']))? static::$param['groupClass'][$group]:null;
    }


    // getDirnameFromValue
    // retourne le dirname à partir d'une valeur passé dans file::new
    public static function getDirnameFromValue($value):?string
    {
        $return = null;

        if(is_string($value))
        $return = Base\Path::dirname($value);

        elseif(Base\Res::is($value))
        $return = Base\Res::dirname($value);

        elseif($value instanceof self)
        $return = $value->dirname();

        return $return;
    }


    // getClassFromDirname
    // retourne la classe à utiliser à partir du dirname (en lien avec storageClass)
    public static function getClassFromDirname(string $value):?string
    {
        $return = null;

        foreach (static::$param['storageClass'] as $class)
        {
            if(is_a($class,Contract\FileStorage::class,true))
            {
                $storage = $class::storageDirname();
                if($storage === $value || Base\Finder::isParent($storage,$value))
                {
                    $return = $class;
                    break;
                }
            }

            else
            static::throw($class,'mustImplement',Contract\FileStorage::class);
        }

        return $return;
    }


    // new
    // crée un nouvel objet fichier
    // la bonne classe sera utilisé pour la création, ceci est déterminé via le mimeGroup
    // il est possible de créer un fichier inexistant dans cette classe, si option a create=>true
    // exception envoyé si le fichier n'est pas créable
    // si value est déjà une instance de file, retourne l'objet directement
    public static function new($value,?array $option=null):self
    {
        $return = null;

        if($value instanceof self)
        $return = $value;

        else
        {
            $class = static::getClass($value,$option);
            $return = new $class($value,$option);
        }

        return $return;
    }


    // newCreate
    // comme new, mais create est true par défaut
    public static function newCreate($value,?array $option=null):self
    {
        return static::newOverload($value,Base\Arr::plus($option,['create'=>true]));
    }


    // newOverload
    // fait un overload sur la classe et ensuite passe à new
    public static function newOverload(...$values):Root
    {
        return static::getOverloadClass()::new(...$values);
    }


    // newFiles
    // retourne un nouvel objet files, overload
    public static function newFiles(...$args):Files
    {
        return Files::newOverload(...$args);
    }


    // registerMime
    // permet d'enregister un mime/extension/group dans base/mime
    public static function registerMime(string $mime,$extension,string $group,$families=null):bool
    {
        return Base\Mime::register($mime,$extension,$group,$families);
    }


    // registerClass
    // permet d'enregister une classe fichier
    // méthode à étendre
    public static function registerClass():bool
    {
        return false;
    }


    // registerGroup
    // permet d'enregister un group class
    // une exception peut être envoyé
    public static function registerGroup(string $name,string $class):void
    {
        if(is_subclass_of($class,self::class,true))
        static::$param['groupClass'][$name] = $class;

        else
        static::throw($name,$class);

        return;
    }


    // registerStorage
    // permet d'enregister une classe de storage
    // une exception peut être envoyé
    public static function registerStorage(string $name,string $class):void
    {
        if(is_subclass_of($class,self::class,true))
        static::$param['storageClass'][$name] = $class;

        else
        static::throw($name,$class);

        return;
    }


    // registerUtil
    // permet d'enregister une classe util
    // une exception peut être envoyé
    public static function registerUtil(string $name,string $class):void
    {
        if(is_subclass_of($class,self::class,true))
        static::$param['utilClass'][$name] = $class;

        else
        static::throw($name,$class);

        return;
    }
}
?>