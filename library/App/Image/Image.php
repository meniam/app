<?php

namespace App\Image;

use App\Image\Exception\ErrorException;
use App\Image\Exception\RuntimeException;

/**
 * Обработка картинок
 *
 * @author Eugene Myazin <meniam@gmail.com>
 */
class Image
{
	// просто растянуть если нужно
	const RESIZE_TYPE_RESIZE   = 1;

	// с соблюдением пропорций
	const RESIZE_TYPE_GEOMETRY = 2;

	// вырезать кусок под размеры
	const RESIZE_TYPE_CROP     = 3;

	// добавить фон
	const RESIZE_TYPE_PAD = 4;

	/**
	 * Исхоный файл
	 *
	 * @var $_file string
	 */
	protected $_file;

	/**
	 * Перезаписывать ли файл
	 */
	protected $_overrideFile = true;

	/**
	 * Результирующий файл
	 *
	 * @var $_file string
	 */
	protected $_outputFile;

	/**
	 *
	 * @var boolean
	 */
	protected $_allowEnlarge = true;

	/**
	 * Тип изменения размера
	 * @var $_resizeType integer
	 */
	protected $_resizeType = self::RESIZE_TYPE_GEOMETRY;

	/**
	 * Размер похожести цвета для trim
	 *
	 * @var $_fuzz integer
	 */
	protected $_fuzz = 10;

	protected $_gravity = 'Center';

	protected $_jpegQuality = 85;

    protected $_backgroundColor = 'white';
    
	/**
	 * Выкидывать ли Exception
	 *
	 * @var $_throwException boolean
	 */
	protected $_throwException = true;

	protected static $_convertBinary = 'convert';

	protected $_filters = array();

    /**
     * Конструктор
     *
     * @param string $file
     * @throws RuntimeException
     */
	public function __construct($file)
	{
		$this->_file = $file;

		if (!$this->isImage() && $this->_throwException) {
            throw new RuntimeException('File is not an image');
		}
	}

    /**
     * Изменить размер картинки
     *
     * @param integer $width
     * @param integer $height
     *
     * @return \App\Image\Image
     */
	public function resize($width = null, $height = null)
	{
		$file = $this->getFile();
		$outputFile = $this->getOutputFile();
		$quality = $this->getJpegQuality();

		$resizeType = $this->_resizeType;

		if (!$width && !$height) {
			$this->copy();
		} else if (!$width || !$height) {
			$resizeType = self::RESIZE_TYPE_GEOMETRY;
		}

		$_filters = $this->getFilters();
		if ($_filters) {
			$filters = implode(' ', $_filters);
		} else {
			$filters = '';
		}

		switch ($resizeType) {
			case self::RESIZE_TYPE_GEOMETRY:
				$width = $width?$width:'';
				$height = $height?$height:'';

                $enlarge = $this->getAllowEnlarge() ? '' : '>';

				$command = "{$filters} -quality {$quality} -geometry '{$width}x{$height}{$enlarge}' '$file' '$outputFile'";

				@system(self::$_convertBinary . ' ' . $command);
				break;

			case self::RESIZE_TYPE_CROP:
				$doubleWidth  = $width * 2;
				$doubleHeight = $height * 2;
				$gravity = $this->getGravity();

				if (strtoupper(substr(PHP_OS, 0,3)) == 'WIN') {
					$command =  "-quality {$quality} -resize x{$doubleHeight} -resize {$doubleWidth}x^< -resize 50%% -gravity {$gravity} -crop {$width}x{$height}+0+0 +repage '{$file}' '{$outputFile}'";
				} else {
					$command = "-quality 100 -resize x{$doubleHeight} -resize '{$doubleWidth}x<' -resize 50% -gravity {$gravity} -crop {$width}x{$height}+0+0 +repage '{$file}' '{$outputFile}'";
                    //$command = "-quality {$quality} -resize x{$height} -resize '{$width}x<' -gravity {$gravity} -crop {$width}x{$height}+0+0 +repage '{$file}' '{$outputFile}'";
                    //$command = "-quality 100 -gravity {$gravity} -crop {$width}x{$height}+0+0 +repage '{$file}' '{$outputFile}'";
                    //$command = "-quality 100 -resize {$width}x{$height}^ -gravity {$gravity} -crop {$width}x{$height}+0+0 -trim +repage '{$file}' '{$outputFile}'";
				}

				@system(self::$_convertBinary . ' ' . $command);
				break;

			case self::RESIZE_TYPE_PAD:
				list($srcWidth, $srcHeight) = $this->getImageInfo();
				$gravity = $this->getGravity();
				$backgroundColor = $this->getBackgroundColor();

                if ($backgroundColor == '#auto') {
                    ob_start();
                    $backgroundColor = @system(self::$_convertBinary . ' ' . "'{$file}'" . " -scale 1x1\!  -format '%[fx:int(255*r)],%[fx:int(255*g)],%[fx:int(255*b)]' info:-");
                    ob_end_clean();

                    $bcolor = '#';
                    foreach(explode(',', $backgroundColor) as $part) {
                        $bcolor .= dechex($part);
                    }

                    $backgroundColor = $bcolor;
                }

				$srcRatio = round($srcWidth / $srcHeight, 2);
				$dstRatio = round($width / $height, 2);

				if ($srcRatio > $dstRatio) {
					$resize = "{$width}x";
					$command = "\\( -size {$width}x{$height} xc:{$backgroundColor} \\) \\( -geometry {$resize} '{$file}' \\) -gravity {$gravity} -composite +repage '{$outputFile}'";
				} else if ($srcRatio < $dstRatio) {
					$resize = "x{$height}";
					$command = "\\( -size {$width}x{$height} xc:{$backgroundColor} \\) \\( -geometry {$resize} '{$file}' \\) -gravity {$gravity} -composite +repage '{$outputFile}'";
				} else {
					$command = "\\( -size {$width}x{$height} xc:{$backgroundColor} \\) \\( -resize {$width}x{$height}^ '{$file}' \\) -gravity {$gravity} -composite +repage '{$outputFile}'";
				}
				@system(self::$_convertBinary . ' ' . $command);

				break;

			case self::RESIZE_TYPE_RESIZE:
				$command = "-quality {$quality} -geometry '{$width}x{$height}!' '{$file}' '{$outputFile}'";
				@system(self::$_convertBinary . ' ' . $command);
				break;
		}

		$result = clone $this;
		$result->setFile($outputFile);
		return $result;
	}


	/**
	 * Обрезать ненужное по краям картинки
	 */
	public function trim($fuzz = null)
	{
		$file       = $this->getFile();
		$outputFile = $this->getOutputFile();
		$fuzz       = $fuzz ? $fuzz : $this->getFuzz();

		if ($fuzz) {
			$command = self::$_convertBinary . " '{$file}' -fuzz {$fuzz}% '{$outputFile}'";
			@system($command);
		} else {
			$this->copy($outputFile);
		}

		$result = clone $this;
		$result->setFile($outputFile);
		return $result;
	}

	/**
	 *
	 * @param string $file
	 * @return $this
	 */
	public function setOutputFile($file)
	{
		$this->_outputFile = (string)$file;
		return $this;
	}

    /**
     * @param string $file
     * @return $this
     */
    public function setFile($file)
	{
		$this->_file = (string)$file;
		return $this;
	}

    /**
     * @throws Exception\ErrorException
     * @return bool|string
     */
    public function getOutputFile()
	{
		if ($this->_outputFile) {
			$outputFile = $this->_outputFile;
		} else {
			$outputFile = $this->_file;
		}

		if (is_file($outputFile) && $this->_overrideFile != true) {
			if ($this->_throwException == true) {
				throw new ErrorException('Destination file exists');
			} else {
				$outputFile = false;
			}
		} else {
			$dir = dirname($outputFile);

			if (!is_dir($dir)) {
				@mkdir($dir, 0775, true);
			}

			if (!is_dir($dir)) {
				if ($this->_throwException) {
					throw new ErrorException('Directory create fail');
				}
			}
		}

		return $outputFile;
	}

    /**
     * @return string
     */
    public function getGravity()
	{
		return $this->_gravity;
	}

    /**
     * @param $gravity
     *
     * @return $this
     */
    public function setGravity($gravity)
    {
        $gravity = strtolower($gravity);
        switch ($gravity) {
            case 'c':
            case 'center':
            case 'm':
            case 'middle':
            case 'd':
            case 'default':
                $this->_gravity = 'Center';
                break;
            case 't':
            case 'top':
            case 'n':
            case 'north':
                $this->_gravity = 'North';
                break;
            case 'b':
            case 'bottom':
            case 's':
            case 'south':
                $this->_gravity = 'South';
                break;
            case 'l':
            case 'left':
            case 'e':
            case 'east':
                $this->_gravity = 'East';
                break;
            case 'r':
            case 'right':
            case 'w':
            case 'west':
                $this->_gravity = 'West';
                break;
            case 'ne':
            case 'northeast':
            case 'north-east':
            case 'tl':
            case 'topleft':
            case 'top-left':
                $this->_gravity = 'NorthEast';
                break;
            case 'nw':
            case 'northwest':
            case 'north-west':
            case 'tr':
            case 'topright':
            case 'top-right':
                $this->_gravity = 'NorthWest';
                break;
            case 'se':
            case 'southeast':
            case 'south-east':
            case 'bl':
            case 'bottomleft':
            case 'bottom-left':
                $this->_gravity = 'SouthEast';
                break;
            case 'sw':
            case 'southwest':
            case 'south-west':
            case 'br':
            case 'bottomright':
            case 'bottom-right':
                $this->_gravity = 'SouthWest';
                break;
        }

        return $this;
    }

    public function copy()
	{
		$outputFile = $this->getOutputFile();

		if ($outputFile) {
			copy($this->getFile(), $outputFile);
		}
	}

    /**
     * @return string
     */
    public function getFile()
	{
		return $this->_file;
	}

    /**
     * @return int
     */
    public function getFuzz()
	{
		return intval($this->_fuzz);
	}

    /**
     * @return int
     */
    public function getJpegQuality()
	{
		return intval($this->_jpegQuality);
	}

    /**
     * @param $jpegQuality
     *
     * @return $this
     */
    public function setJpegQuality($jpegQuality)
    {
        $this->_jpegQuality = intval($jpegQuality);
        return $this;
    }

	/**
	 * Соблюдать геометрию картинки?
	 *
	 * @param integer $resizeType see self::RESIZE_TYPE_*
	 * @return $this
	 */
	public function setResizeType($resizeType = self::RESIZE_TYPE_GEOMETRY)
	{
		$this->_resizeType = $resizeType;
		return $this;
	}

    /**
     * @param $filter
     * @return $this
     */
	public function addFilter($filter)
	{
		$this->_filters[] = $filter;
		return $this;
	}

    /**
     * @return array
     */
    public function getFilters()
	{
		return $this->_filters;
	}

    /**
     * @return bool
     */
    public function isImage()
	{
		$fileInfo = $this->getImageInfo();

		return in_array($fileInfo['mime'], array('image/jpeg', 'image/jpg', 'image/gif','image/png'));
	}

    /**
     * Получить информацию о картинке
     *
     * @param bool|string $var
     * @return array
     */
	public function getImageInfo($var = false)
	{
		$imageInfo = getimagesize($this->_file);

		if (!$var) {
			return $imageInfo;
		}
	}

    /**
     * @return bool
     */
    public function exists()
	{
		return is_file($this->_file);
	}

	/**
	 * Созадть самого себя
	 *
	 * @param string $file
	 * @return $this
	 */
	public static function init($file)
	{
		return new self($file);
	}

    /**
     * Выкидывать ли Exception
     *
     * @param boolean $flag
     * @return $this
     */
    public function setThrowException($flag)
	{
		$this->_throwException = (bool)$flag;
        return $this;
	}

    /**
     * @param boolean|string $allowEnlarge
     * @return $this
     */
    public function setAllowEnlarge($allowEnlarge)
    {
        if ($allowEnlarge == 'y') {
            $allowEnlarge = true;
        } elseif ($allowEnlarge == 'n' || $allowEnlarge == 'd') {
            $allowEnlarge = false;
        }

        $this->_allowEnlarge = (bool)$allowEnlarge;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAllowEnlarge()
    {
        return $this->_allowEnlarge;
    }

    /**
     * @param $backgroundColor
     *
     * @return $this
     */
    public function setBackgroundColor($backgroundColor)
    {
        $this->_backgroundColor = $backgroundColor;
        return $this;
    }

    /**
     * @return string
     */
    public function getBackgroundColor()
    {
        return $this->_backgroundColor;
    }
}
