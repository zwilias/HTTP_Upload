<?php
// **********************************************
//
// This software is licensed by the LGPL
// -> http://www.gnu.org/copyleft/lesser.txt
// (c) 2001 by Tomas Von Veschler
//
// **********************************************
//
// $Id$

/**
* Pear File Uploader class. Easy and secure managment of files
* submitted via HTML Forms.
*
* @see http://vulcanonet.com/soft/index.php?pack=uploader
* @author Tomas V.V.Cox <cox@vulcanonet.com>
*
*
* TODO:
* - PHPDoc clean-up
*
* Leyend:
* - you can add error msgs in your language in the HTTP_Upload_Error class
*/

require_once 'PEAR.php';

class HTTP_Upload_Error extends PEAR
{
    /**
    * Selected language for error messages
    */
    var $lang = 'en';

    /**
    * Constructor
    *
    * @param string $lang The language selected for error code messages
    */
    function HTTP_Upload_Error($lang = null)
    {
        $this->lang = ($lang !== null) ? $lang : $this->lang;
        global $HTTP_POST_VARS;
        $maxsize = (isset($HTTP_POST_VARS['MAX_FILE_SIZE'])) ? $HTTP_POST_VARS['MAX_FILE_SIZE'] : null;
        $ini_size = ini_get('upload_max_filesize');
        if (empty($maxsize) || ($maxsize > $ini_size)) {
            $maxsize = $ini_size;
        }
        // XXXXX Add here error messages in your language
        $this->error_codes = array(
            'TOO_LARGE' => array(
                'es' => "Fichero demasiado largo. El maximo permitido es: $maxsize bytes",
                'en' => "Too long file size. The maximun permited size is: $maxsize bytes"
                ),
            'MISSING_DIR' => array(
                'es' => 'Falta directorio destino',
                'en' => 'Missing destination directory'
                ),
            'IS_NOT_DIR' => array(
                'es' => 'El directorio destino no existe o es un fichero regular',
                'en' => 'The destination directory doesn\'t exist or is a regular file'
                ),
            'NO_WRITE_PERMS' => array(
                'es' => 'El directorio destino no tiene permisos de escritura',
                'en' => 'The destination directory doesn\'t have write perms'
                ),
            'NO_USER_FILE' => array(
                'es' => 'No se ha escogido fichero para el upload',
                'en' => 'You haven\'t selected any file for uploading'
                ),
            'BAD_FORM' => array(
                'es' => 'El formulario no contiene METHOD="post" ENCTYPE="multipart/form-data" requerido',
                'en' => 'The html form doesn\'t contain the required METHOD="post" ENCTYPE="multipart/form-data"'
                ),
            'E_FAIL_COPY' => array(
                'es' => 'Fallo al copiar el fichero temporal',
                'en' => 'Fail to copy the temp file'
                ),
            'FILE_EXISTS' => array(
                'es' => 'El fichero destino ya existe',
                'en' => 'The destination file yet exists'
                ),
            'CANNOT_OVERWRITE' => array(
                'es' => 'El fichero destino ya existe y no se puede sobreescribir',
                'en' => 'The destination file yet exists and could not be overwritten'
                )
        );
    }

    function errorCode($e_code)
    {
        if (isset($this->lang) &&
            !empty($this->error_codes[$e_code][$this->lang]))
        {
            $msg = $this->error_codes[$e_code][$this->lang];
        } else {
            $msg = $e_code;
        }
        return 'Upload Error: '. $msg;
    }

    function raiseError($e_code)
    {
        return PEAR::raiseError($this->errorCode($e_code));
    }
}

class HTTP_Upload extends HTTP_Upload_Error
{
    var $files = array();

    /**
    * Constructor
    * @param string $lang Language to use for reporting errors
    * @see Upload_Error::error_codes
    */
    function HTTP_Upload($lang = null)
    {
        $this->HTTP_Upload_Error($lang);
        global $HTTP_POST_FILES, $HTTP_SERVER_VARS;
        $this->post_files   = $HTTP_POST_FILES;
        $this->content_type = $HTTP_SERVER_VARS['CONTENT_TYPE'];
    }

    /**
    * Get files
    *
    * @param mixed $file If:
    *    - not given, function will return array of upload_file objects
    *    - is int, will return the $file position in upload_file objects array
    *    - is string, will return the upload_file object corresponding
    *        to $file name of the form. For ex:
    *        if form is <input type="file" name="userfile">
    *        to get this file use: $upload->getFiles('userfile')
    *
    * @return mixed array or object (see @param $file above) or Pear_Error
    */
    function &getFiles($file = null)
    {
        //build only once for multiple calls
        if (!isset($this->is_built)) {
            $this->files = $this->_buildFiles();
            if (PEAR::isError($this->files)) {
                return $this->files;
            }
            $this->is_built = true;
        }
        if ($file !== null) {
            if (is_int($file)) {
                $pos = 0;
                foreach ($this->files as $key => $obj) {
                    if ($pos == $file) {
                        return $obj;
                    }
                    $pos++;
                }
            } elseif (is_string($file) && isset($this->files[$file])) {
                return $this->files[$file];
            }
            return $this->raiseError('requested file not found'); // XXXX
        }
        return $this->files;
    }

    function &_buildFiles()
    {
        // Form method check
        if (empty($this->post_files) ||
            !ereg('multipart/form-data', $this->content_type)) {
                return $this->raiseError('BAD_FORM');
        }

        // Parse $HTTP_POST_FILES
        $files = array();
        foreach ($this->post_files as $userfile => $value) {
            if (is_array($value['name'])) {
                foreach ($value['name'] as $key => $val) {
                    $name = $value['name'][$key];
                    $tmp_name = $value['tmp_name'][$key];
                    $size = $value['size'][$key];
                    $type = $value['type'][$key];
                    $formname = $userfile . "[$key]";
                    $files[$formname] = new HTTP_Upload_File($name, $tmp_name,
                                            $formname, $type, $size, $this->lang);
                }
            // One file
            } else {
                $name = $value['name'];
                $tmp_name = $value['tmp_name'];
                $size = $value['size'];
                $type = $value['type'];
                $formname = $userfile;
                $files[$formname] = new HTTP_Upload_File($name, $tmp_name,
                                            $formname, $type, $size, $this->lang);
            }
        }
        return $files;
    }
}

class HTTP_Upload_File extends HTTP_Upload_Error
{
    /**
    * Assoc array with file properties
    */
    var $upload = array();

    function HTTP_Upload_File ($name=null, $tmp=null,  $formname=null,
                               $type=null, $size=null, $lang=null)
    {
        $this->HTTP_Upload_Error($lang);
        $error = null;
        $ext   = null;
        if (empty($name)) {
            $error = 'NO_USER_FILE';
        } elseif ($tmp == 'none') {
            $error = 'TOO_LARGE';
        } else {
            // strpos needed to detect files without extension
            if (($pos = strrpos($name, '.')) !== false) {
                $ext = substr ($name, $pos + 1);
            }
        }

        $this->upload = array(
            'real'      => $name,
            'name'      => $name,
            'form_name' => $formname,
            'ext'       => $ext,
            'tmp_name'  => $tmp,
            'size'      => $size,
            'type'      => $type,
            'error'     => $error
        );
    }

    /**
    * Sets the name of the destination file
    *
    * @param string $mode A valid mode: 'uniq', 'safe' or 'real' or a file name
    * @param string $prepend A string to prepend to the name
    * @param string $append A string to append to the name
    *
    * @return string The modified name of the destination file
    */
    function setName ($mode, $prepend=null, $append=null)
    {
        switch ($mode) {
            case 'uniq':
                $name = $this->nameToUniq();
                break;
            case 'safe':
                $name = $this->nameToSafe($this->upload['real']);
                break;
            case 'real':
                $name = $this->upload['real'];
                break;
            default:
                $name = $mode;
        }
        $this->upload['name'] = $prepend . $name . $append;
        return $this->upload['name'];
    }
    /**
    * Unique file names in the form: 9022210413b75410c28bef.html
    */
    function nameToUniq ()
    {
        srand((double) microtime() * 1000000);
        $uniq = uniqid(rand());
        return $uniq . '.' . $this->nameToSafe($this->upload['ext'],10);
    }

    /**
    * Dada una cadena de texto, la formatea para que
    * se pueda convertir en un nombre de fichero seguro
    *
    * @param string $file  - La cadena de texto a convertir
    * @param int $maxlen - Maximun permited string lenght
    * @result string - Cadena de texto con formato de nombre de fichero
    */
    function nameToSafe ($name, $maxlen=250)
    {
        $noalpha = 'áéíóúàèìòùäëïöüÁÉÍÓÚÀÈÌÒÙÄËÏÖÜâêîôûÂÊÎÔÛñçÇ@';
        $alpha =   'aeiouaeiouaeiouAEIOUAEIOUAEIOUaeiouAEIOUncCa';
        // el largo del nombre del fichero no debe exceder los 200 cc
        // se dejan 55 cc para poder poner extensiones y otros datos
        $name = substr ($name, 0, $maxlen);
        $name = strtr ($name, $noalpha, $alpha);
        // si no es un caracter permitido, se substituye por "_"
        return ereg_replace ('[^a-zA-Z0-9/,._\+\()\-]', '_', $name);
    }
    /**
    * @return bool If the file was submitted correctly
    */
    function isValid()
    {
        if ($this->upload['error'] === null) {
            return true;
        }
        return false;
    }
    /**
    * @return bool If the user submitted a file or not
    */
    function isMissing()
    {
        if ($this->upload['error'] == 'NO_USER_FILE') {
            return true;
        }
        return false;
    }
    /**
    * @return bool If there were errors submitting the file (probably
    *              because the file excess the max permitted file size)
    */
    function isError()
    {
        if ($this->upload['error'] == 'TOO_LARGE') {
            return true;
        }
        return false;
    }

    /**
    * Copia el fichero subido numero $filepos, al directorio $dir_dest
    * con nombre $name_dest.
    *
    * @param string $dir_dest
    * @param bool $overwrite
    * @return mixed True on success or Pear_Error object on errors
    */
    function moveTo ($dir_dest, $overwrite=true)
    {
        if (OS_WINDOWS) {
            return $this->raiseError('not tested yet');
        }
        if (!$this->isValid()) {
            return $this->raiseError ($this->upload['error']);
        }
        $err_code = $this->_chk_dir_dest($dir_dest);
        if ($err_code !== false) {
            return $this->raiseError($err_code);
        }
        $slash = '';
        if ($dir_dest[strlen($dir_dest)-1] != '/') {
            $slash = '/';
        }
        $name_dest = $dir_dest . $slash . $this->upload['name'];

        $is_file = @is_file($name_dest);

        if (($overwrite !== true) && $is_file) {
            return $this->raiseError('FILE_EXISTS');
        }
        if ($is_file && !is_writable($name_dest)) {
            return $this->raiseError('CANNOT_OVERWRITE');
        }
        // Copy the file and let php clean the tmp
        if (!@copy ($this->upload['tmp_name'], $name_dest)) {
            return $this->raiseError('E_FAIL_MOVE');
        }
        @chmod ($name_dest, 0660);
        return $this->getProp('name');
    }

    /**
    * Check for a valid destination dir
    *
    * @param string $dir_dest Destination dir
    * @return mixed False on no errors or error code on error
    * @access private
    */
    function _chk_dir_dest ($dir_dest)
    {
        if (!$dir_dest) {
            return 'MISSING_DIR';
        }
        if (!@is_dir ($dir_dest)) {
            return 'IS_NOT_DIR';
        }
        if (!is_writeable ($dir_dest)) {
            return 'NO_WRITE_PERMS';
        }
        return false;
    }
    /**
    * Retrive properties of the uploaded file
    * @param string $name The property name. When null an assoc array with
    *                     all the properties will be returned
    * @return mixed A string or array
    * @see HTTP_Upload_File::HTTP_Upload_File()
    */
    function getProp ($name=null)
    {
        if ($name === null) {
            return $this->upload;
        }
        return $this->upload[$name];
    }

    function errorMsg()
    {
        return $this->errorCode($this->upload['error']);
    }
}
?>