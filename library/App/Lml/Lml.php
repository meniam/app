<?php

namespace App\Lml;

use App\Exception\ErrorException;

/**
 * Description
 *
 * @category   category
 * @package    package
 * @author     Eugene Myazin <meniam@gmail.com>
 * @since      19.12.12 12:18
 * @copyright  2008-2012 ООО "Америка"
 * @version    SVN: $Id$
 */
class Lml
{
    const TYPE_JS = 'js';
    const TYPE_CSS = 'css';

    private $fileArray = array();

    public function __construct($documentRoot)
    {
        $this->documentRoot = rtrim($documentRoot, ' \/\\');

        if (!is_dir($this->documentRoot)) {
            throw new ErrorException('Document root folder not exists');
        }
    }

    /**
     * @param        $file
     * @param string $type
     *
     * @throws ErrorException
     * @return Lml
     */
    public function addFile($file, $type = self::TYPE_JS)
    {
        if (!is_file($file)) {
            throw new ErrorException("File not exists: " . $file);
        }

        $this->fileArray[$type][$file] = $file;
        return $this;
    }

    /**
     * @param null $type
     * @return array
     */
    public function getFileArray($type = null)
    {
        if (!$type) {
            return $this->fileArray;
        }

        if (isset($this->fileArray[$type])) {
            return $this->fileArray[$type];
        } else {
            return array();
        }
    }

    public function getCombinedDataByType($type)
    {
        $fileArray = $this->getFileArray($type);

        $contents = '';
        $delimiter = $this->getDelimiterByType($type);
        if (!empty($fileArray)) {
            foreach ($fileArray as $file) {
                $contents .= (file_get_contents($file) . $delimiter);
            }
        }

        return $contents;
    }

    /**
     * @param $filename
     * @param $type
     * @return Lml
     */
    public function saveToFile($filename, $type)
    {
        $data = $this->getCombinedDataByType($type) . $this->getJavaScriptFileRegistry();

        file_put_contents($filename, $data);

        return $this;
    }

    public function getJavaScriptFileRegistry()
    {
        $result = "\n\nvar nmodule_loaded_files = [];\n\n";

        $_fileArray = $this->getFileArray();

        foreach ($_fileArray as $fileArray) {
            foreach ($fileArray as $file) {
                $relativeFilename = str_replace($this->documentRoot, '', $file);
                //$result .= "\n\nmodule_loaded_files.push('{$relativeFilename}');\n\n";
                $result .= "\n\n$.lml.registerFile('{$relativeFilename}');\n\n";
            }
        }

        return $result;
    }

    /**
     * @param $type
     * @return string
     */
    public function getDelimiterByType($type)
    {
        switch ($type) {
            case self::TYPE_JS:
                return ";\n\n";
            default:
                return "\n\n";
        }
    }

    public function compileConfig($config)
    {
        if (isset($config['modules'])) {
            foreach ($config['modules'] as &$params) {
                $init = '';
                if (isset($params['init_js'])) {
                    $filename = $this->documentRoot . $params['init_js'];
                    if (!is_file($filename)) {
                        throw new ErrorException('File not found' . $filename);
                    }
                    $init = file_get_contents($filename);
                }
                $params['init'] = $init;

                if (isset($params['compile']) && $params['compile'] == false) {
                    continue;
                }

                $jsContent = '';
                if (isset($params['js'])) {
                    foreach ($params['js'] as $jsFile) {
                        $filename = $this->documentRoot . $jsFile;
                        if (!is_file($filename)) {
                            throw new ErrorException('File not found' . $filename);
                        }
                        $jsContent .= file_get_contents($filename) . $this->getDelimiterByType(self::TYPE_JS);
                    }

                }
                $params['script'] = $jsContent;

                unset($params['js'], $params['init_js']);
            }

        }


        return json_encode($config);
    }
}
