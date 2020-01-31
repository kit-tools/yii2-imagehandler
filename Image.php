<?php
namespace kittools\imagehandler;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Yii;
use yii\imagine\BaseImage;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Point;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\helpers\ArrayHelper;
use Imagine\Image\Palette\RGB;

class Image
{
    /**
     * GD2 driver definition for Imagine implementation using the GD library.
     */
    const DRIVER_GD2 = 'gd2';
    /**
     * imagick driver definition.
     */
    const DRIVER_IMAGICK = 'imagick';
    /**
     * gmagick driver definition.
     */
    const DRIVER_GMAGICK = 'gmagick';

    const POSITION_TOP = 'T';
    const POSITION_RIGHT = 'R';
    const POSITION_BOTTOM = 'B';
    const POSITION_LEFT = 'L';
    const POSITION_CENTER = 'C';
    const POSITION_TOP_LEFT = 'TL';
    const POSITION_TOP_RIGHT = 'TR';
    const POSITION_BOTTOM_LEFT = 'BL';
    const POSITION_BOTTOM_RIGHT = 'BR';
    const POSITION_CENTER_LEFT = 'CL';
    const POSITION_CENTER_RIGHT = 'CR';
    const POSITION_CENTER_TOP = 'CT';
    const POSITION_CENTER_BOTTOM = 'CB';

    protected static $_positions = [
        self::POSITION_TOP,
        self::POSITION_RIGHT,
        self::POSITION_BOTTOM,
        self::POSITION_LEFT,
        self::POSITION_CENTER,
        self::POSITION_TOP_LEFT,
        self::POSITION_TOP_RIGHT,
        self::POSITION_BOTTOM_LEFT,
        self::POSITION_BOTTOM_RIGHT,
        self::POSITION_CENTER_LEFT,
        self::POSITION_CENTER_RIGHT,
        self::POSITION_CENTER_TOP,
        self::POSITION_CENTER_BOTTOM
    ];

    const FIT_WITHIN_INCREASE = 1; //увеличить
    const FIT_WITHIN_REDUSE = 2; //уменьшить
    const FIT_WITHIN_INCREASE_AND_REDUSE = 3; //увеличить и уменьшить

    /**
     * @var array|string the driver to use. This can be either a single driver name or an array of driver names.
     * If the latter, the first available driver will be used.
     */
    public static $driver = [self::DRIVER_GMAGICK, self::DRIVER_IMAGICK, self::DRIVER_GD2];

    /**
     * @var ImagineInterface instance.
     */
    private static $_imagine;

    /**
     * @var string background color to use when creating thumbnails in `ImageInterface::THUMBNAIL_INSET` mode with
     * both width and height specified. Default is white.
     *
     * @since 2.0.4
     */
    const BACKGROUND_COLOR = 'ffffff';

    /**
     * @var string background alpha (transparency) to use when creating thumbnails in `ImageInterface::THUMBNAIL_INSET`
     * mode with both width and height specified. Default is solid.
     *
     * @since 2.0.4
     */
    const BACKGROUND_ALPHA = null;

    /**
     * Returns the `Imagine` object that supports various image manipulations.
     * @return ImagineInterface the `Imagine` object
     */
    public static function getImagine()
    {
        if (self::$_imagine === null) {
            self::$_imagine = static::createImagine();
        }

        return self::$_imagine;
    }

    /**
     * @param ImagineInterface $imagine the `Imagine` object.
     */
    public static function setImagine($imagine)
    {
        self::$_imagine = $imagine;
    }

    /**
     * Creates an `Imagine` object based on the specified [[driver]].
     * @return ImagineInterface the new `Imagine` object
     * @throws InvalidConfigException if [[driver]] is unknown or the system doesn't support any [[driver]].
     */
    protected static function createImagine()
    {
        foreach ((array)static::$driver as $driver) {
            switch ($driver) {
                case self::DRIVER_GMAGICK:
                    if (class_exists('Gmagick', false)) {
                        return new \Imagine\Gmagick\Imagine();
                    }
                    break;
                case self::DRIVER_IMAGICK:
                    if (class_exists('Imagick', false)) {
                        return new \Imagine\Imagick\Imagine();
                    }
                    break;
                case self::DRIVER_GD2:
                    if (function_exists('gd_info')) {
                        return new \Imagine\Gd\Imagine();
                    }
                    break;
                default:
                    throw new InvalidConfigException("Unknown driver: $driver");
            }
        }
        throw new InvalidConfigException("Your system does not support any of these drivers: " . implode(',',
                (array)static::$driver));
    }

    public static function crop($filename, $width, $height, $position = [0, 0])
    {
        $img = static::getImagine()->open($filename);

        if (is_array($position) && !isset($position[0], $position[1])) {
            throw new \BadMethodCallException('$position must be an array of two elements.');
        }

        if (is_string($position)) {
            $position = self::calculatePosition($img->getSize()->getWidth(), $img->getSize()->getHeight(), $width,
                $height, $position);
        }

        return $img
            ->copy()
            ->crop(new Point($position[0], $position[1]), new Box($width, $height));
    }

    public static function resize(
        $filename,
        $width,
        $height,
        $keepAspectRatio = true,
        $fitWithin = self::FIT_WITHIN_INCREASE_AND_REDUSE,
        $filter = ImageInterface::FILTER_UNDEFINED
    ) {
        $img = static::getImagine()->open($filename);

        if ($width === null && $height === null) {
            throw new \BadMethodCallException('Please specify at least one parameter $width or $height.');
        }

        if ($width === null) {
            $ratio = $img->getSize()->getHeight() / $img->getSize()->getWidth();
            $width = ceil($height / $ratio);
        } else {
            if ($height === null) {
                $ratio = $img->getSize()->getWidth() / $img->getSize()->getHeight();
                $height = ceil($width / $ratio);
            } else {
                if ($keepAspectRatio && $img->getSize()->getWidth() != $img->getSize()->getHeight()) {
                    if ($img->getSize()->getWidth() > $img->getSize()->getHeight()) {
                        $ratio = $img->getSize()->getWidth() / $img->getSize()->getHeight();
                        $height = ceil($width / $ratio);
                    }
                    if ($img->getSize()->getWidth() < $img->getSize()->getHeight()) {
                        $ratio = $img->getSize()->getHeight() / $img->getSize()->getWidth();
                        $width = ceil($height / $ratio);
                    }
                }
            }
        }


        return self::fitWithin($fitWithin, $img->getSize()->getWidth(), $img->getSize()->getHeight(), $width, $height)
            ? $img->resize(new Box($width, $height), $filter) : $img;
    }

    public function rotate(
        $filename,
        $angle,
        $backgroundColor = self::BACKGROUND_COLOR,
        $alpha = self::BACKGROUND_ALPHA
    ) {
        $img = static::getImagine()->open($filename);

        return $img->rotate($angle, (new RGB())->color($backgroundColor, $alpha));
    }

    /*public function paste(ImageInterface $image, PointInterface $start)
    {
    }*/

    /*public function save($path, array $options = array())
    {
    }*/

    /*public function show($format, array $options = array())
    {
    }*/

    /*public function flipHorizontally()
    {
    }*/

    /*public function flipVertically()
    {
    }*/

    /*public function strip()
    {
    }*/

    public static function thumbnail(
        $filename,
        $width,
        $height,
        $mode = ManipulatorInterface::THUMBNAIL_OUTBOUND,
        $position = self::POSITION_CENTER,
        $backgroundColor = self::BACKGROUND_COLOR,
        $alpha = self::BACKGROUND_ALPHA
    ) {
        $img = static::getImagine()->open($filename);

        $thumbnail = $img->thumbnail(new Box($width, $height), $mode);

        if (is_array($position) && !isset($position[0], $position[1])) {
            throw new \BadMethodCallException('$position must be an array of two elements.');
        }

        if (is_string($position)) {
            $position = self::calculatePosition($thumbnail->getSize()->getWidth(),
                $thumbnail->getSize()->getHeight(), $width, $height, $position);
        }

        $thumbnailBox = static::getImagine()->create(new Box($width, $height), (new RGB())->color($backgroundColor, $alpha));
        return $thumbnailBox->paste($thumbnail, new Point(abs($position[0]), abs($position[1])));
    }

    /*public function applyMask(ImageInterface $mask)
    {
    }*/

    /*public function fill(FillInterface $fill)
    {
    }*/

    public static function watermark($filename, $watermarkFilename, $position = self::POSITION_CENTER)
    {
        $img = static::getImagine()->open(Yii::getAlias($filename));
        $watermark = static::getImagine()->open(Yii::getAlias($watermarkFilename));

        if ($watermark->getSize()->getWidth() > $img->getSize()->getWidth() || $watermark->getSize()->getHeight() > $img->getSize()->getHeight()) {
            $watermark = static::resize(
                \Yii::getAlias($watermarkFilename),
                $img->getSize()->getWidth(),
                $watermark->getSize()->getHeight(),
                true,
                self::FIT_WITHIN_REDUSE
            );
        }

        if (is_array($position) && !isset($position[0], $position[1])) {
            throw new \BadMethodCallException('$position must be an array of two elements.');
        }

        if (is_string($position)) {
            $position = self::calculatePosition($img->getSize()->getWidth(), $img->getSize()->getHeight(),
                $watermark->getSize()->getWidth(), $watermark->getSize()->getHeight(), $position);
        }

        $img->paste($watermark, new Point($position[0], $position[1]));

        return $img;
    }

    public function calculatePosition($imageWidth, $imageHeight, $cropWidth, $cropHeight, $position)
    {
        $startX = floor(($imageWidth - $cropWidth) / 2);
        $startY = floor(($imageHeight - $cropHeight) / 2);
        switch ($position) {
            case self::POSITION_TOP:
            case self::POSITION_LEFT:
            case self::POSITION_TOP_LEFT:
                return [0, 0];
                break;
            case self::POSITION_RIGHT:
            case self::POSITION_TOP_RIGHT:
                return [($imageWidth - $cropWidth), 0];
                break;
            case self::POSITION_BOTTOM:
            case self::POSITION_BOTTOM_LEFT:
                return [0, ($imageHeight - $cropHeight)];
                break;
            case self::POSITION_CENTER:
                return [$startX, $startY];
                break;
            case self::POSITION_BOTTOM_RIGHT:
                return [($imageWidth - $cropWidth), ($imageHeight - $cropHeight)];
                break;
            case self::POSITION_CENTER_LEFT:
                return [0, $startY];
                break;
            case self::POSITION_CENTER_RIGHT:
                return [($imageWidth - $cropWidth), $startY];
                break;
            case self::POSITION_CENTER_TOP:
                return [$startX, 0];
                break;
            case self::POSITION_CENTER_BOTTOM:
                return [$startX, ($imageHeight - $cropHeight)];
                break;
        }
        throw new \InvalidArgumentException('Unsupported position. Specify the position of the Image::POSITION_*.');
    }

    protected static function fitWithin($fitValue, $originalWidth, $originalHeight, $newWidth, $newHeight)
    {
        switch ($fitValue) {
            case self::FIT_WITHIN_INCREASE:
                return ($originalWidth < $newWidth || $originalHeight < $newHeight) ? true : false;
                break;
            case self::FIT_WITHIN_REDUSE:
                return ($originalWidth > $newWidth || $originalHeight > $newHeight) ? true : false;
                break;
            case self::FIT_WITHIN_INCREASE_AND_REDUSE:
                return true;
                break;
        }
        throw new \InvalidArgumentException('Unsupported value. Specify the value of the Image::FIT_WITHIN_*.');
    }
}