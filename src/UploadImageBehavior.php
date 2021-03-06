<?php

namespace mohorev\file;

use Imagine\Image\ManipulatorInterface;
use Yii;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\imagine\Image;

/**
 * UploadImageBehavior automatically uploads image, creates thumbnails and fills
 * the specified attribute with a value of the name of the uploaded image.
 *
 * To use UploadImageBehavior, insert the following code to your ActiveRecord class:
 *
 * ```php
 * use mohorev\file\UploadImageBehavior;
 *
 * function behaviors()
 * {
 *     return [
 *         [
 *             'class' => UploadImageBehavior::class,
 *             'attribute' => 'file',
 *             'scenarios' => ['insert', 'update'],
 *             'placeholder' => '@app/modules/user/assets/images/userpic.jpg',
 *             'path' => '@webroot/upload/{id}/images',
 *             'url' => '@web/upload/{id}/images',
 *             'thumbPath' => '@webroot/upload/{id}/images/thumb',
 *             'thumbUrl' => '@web/upload/{id}/images/thumb',
 *             'createThumbsOnSave' => false,
 *             'createThumbsOnRequest' => true,
 *             'thumbs' => [
 *                   'thumb' => ['width' => 400, 'quality' => 90],
 *                   'preview' => ['width' => 200, 'height' => 200],
 *              ],
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Alexander Mohorev <dev.mohorev@gmail.com>
 * @author Alexey Samoylov <alexey.samoylov@gmail.com>
 */
class UploadImageBehavior extends UploadBehavior
{
    /**
     * @var string
     */
    public $placeholder;
    /**
     * create all thumbs profiles on image upload
     * @var boolean
     */
    public $createThumbsOnSave = true;
    /**
     * create thumb only for profile request by getThumbUploadUrl() method
     * @var boolean
     */
    public $createThumbsOnRequest = false;
    /**
     * Whether delete original uploaded image after thumbs generating.
     * Defaults to FALSE
     * @var boolean
     */
    public $deleteOriginalFile = false;
    /**
     * @var array the thumbnail profiles
     * - `width`
     * - `height`
     * - `quality`
     */
    public $thumbs = [
        'thumb' => ['width' => 200, 'height' => 200, 'quality' => 90],
    ];
    /**
     * @var string|null
     */
    public $thumbPath;
    /**
     * @var string|null
     */
    public $thumbUrl;


    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!class_exists(Image::class)) {
            throw new NotSupportedException("Yii2-imagine extension is required to use the UploadImageBehavior");
        }

        parent::init();

        if ($this->thumbPath === null) {
            $this->thumbPath = $this->path;
        }
        if ($this->thumbUrl === null) {
            $this->thumbUrl = $this->url;
        }

        foreach ($this->thumbs as $config) {
            $width = ArrayHelper::getValue($config, 'width');
            $height = ArrayHelper::getValue($config, 'height');
            if ($height < 1 && $width < 1) {
                throw new InvalidConfigException(sprintf(
                    'Length of either side of thumb cannot be 0 or negative, current size ' .
                    'is %sx%s', $width, $height
                ));
            }
        }
    }

    /**
     * @param string $attribute
     * @param string $profile
     * @return string|null
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function getThumbUploadUrl($attribute, $profile = 'thumb')
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;

        if ($this->attribute !== $attribute) {
            $behaviors = $model->getBehaviors();

            foreach ($behaviors as $behavior) {
                if ($behavior instanceof UploadImageBehavior) {
                    if ($behavior->attribute == $attribute) {
                        return $behavior->getThumbUploadUrl($attribute, $profile);
                    }
                }
            }
        }

        if (!$model->getAttribute($attribute)) {
            if ($this->placeholder) {
                return $this->getPlaceholderUrl($profile);
            } else {
                return null;
            }
        }

        $path = $this->getUploadPath($attribute, true);

        //if original file exist - generate profile thumb and generate url to thumb
        if (is_file($path) || !$this->deleteOriginalFile) {
            if ($this->createThumbsOnRequest) {
                $this->createThumbs($profile);
            }
            return $this->getThumbProfileUrl($attribute, $profile, $model);
        } //if original file is deleted generate url to thumb
        elseif ($this->deleteOriginalFile) {
            return $this->getThumbProfileUrl($attribute, $profile, $model);
        } elseif ($this->placeholder) {
            return $this->getPlaceholderUrl($profile);
        } else {
            return null;
        }
    }

    /**
     * @param $profile
     * @return string
     */
    protected function getPlaceholderUrl($profile)
    {
        list ($path, $url) = Yii::$app->assetManager->publish($this->placeholder);
        $filename = basename($path);
        $thumb = $this->getThumbFileName($filename, $profile);
        $thumbPath = dirname($path) . DIRECTORY_SEPARATOR . $thumb;
        $thumbUrl = dirname($url) . '/' . $thumb;

        if (!is_file($thumbPath)) {
            $this->generateImageThumb($this->thumbs[$profile], $path, $thumbPath);
        }

        return $thumbUrl;
    }

    /**
     * @param $attribute
     * @param $profile
     * @param BaseActiveRecord $model
     * @return bool|string
     */
    protected function getThumbProfileUrl($attribute, $profile, BaseActiveRecord $model)
    {
        $url = $this->resolvePath($this->thumbUrl);
        $fileName = $model->getOldAttribute($attribute);
        $thumbName = $this->getThumbFileName($fileName, $profile);

        return Yii::getAlias($url . '/' . $thumbName);
    }

    /**
     * @inheritdoc
     */
    protected function afterUpload()
    {
        parent::afterUpload();
        if ($this->createThumbsOnSave) {
            $this->createThumbs();
        }
    }

    /**
     * @param string $needed_profile - profile name to create thumb
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    protected function createThumbs($needed_profile = false)
    {
        $path = $this->getUploadPath($this->attribute);
        foreach ($this->thumbs as $profile => $config) {
            //skip profiles not needed now
            if ($needed_profile && $needed_profile != $profile) {
                continue;
            }

            $thumbPath = $this->getThumbUploadPath($this->attribute, $profile);
            if ($thumbPath !== null) {
                if (!FileHelper::createDirectory(dirname($thumbPath))) {
                    throw new InvalidArgumentException(
                        "Directory specified in 'thumbPath' attribute doesn't exist or cannot be created."
                    );
                }
                if (!is_file($thumbPath) && !file_exists($thumbPath)) {
                    $this->generateImageThumb($config, $path, $thumbPath);
                }
            }
        }

        if ($this->deleteOriginalFile) {
            $this->deleteFile($path);
        }
    }

    /**
     * @param string $attribute
     * @param string $profile
     * @param boolean $old
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public function getThumbUploadPath($attribute, $profile = 'thumb', $old = false)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        $path = $this->resolvePath($this->thumbPath);
        $attribute = ($old === true) ? $model->getOldAttribute($attribute) : $model->$attribute;
        $filename = $this->getThumbFileName($attribute, $profile);

        return $filename ? Yii::getAlias($path . '/' . $filename) : null;
    }

    /**
     * @param $filename
     * @param string $profile
     * @return string
     */
    protected function getThumbFileName($filename, $profile = 'thumb')
    {
        return $profile . '-' . $filename;
    }

    /**
     * @param $config
     * @param $path
     * @param $thumbPath
     */
    protected function generateImageThumb($config, $path, $thumbPath)
    {
        $width = ArrayHelper::getValue($config, 'width');
        $height = ArrayHelper::getValue($config, 'height');
        $quality = ArrayHelper::getValue($config, 'quality', 100);
        $mode = ArrayHelper::getValue($config, 'mode', ManipulatorInterface::THUMBNAIL_INSET);
        $bg_color = ArrayHelper::getValue($config, 'bg_color', 'FFF');

        if (!$width || !$height) {
            $image = Image::getImagine()->open($path);
            $ratio = $image->getSize()->getWidth() / $image->getSize()->getHeight();
            if ($width) {
                $height = ceil($width / $ratio);
            } else {
                $width = ceil($height * $ratio);
            }
        }

        // Fix error "PHP GD Allowed memory size exhausted".
        ini_set('memory_limit', '512M');
        //for big images size
        ini_set('max_execution_time', 60);
        Image::$thumbnailBackgroundColor = $bg_color;
        Image::thumbnail($path, $width, $height, $mode)->save($thumbPath, ['quality' => $quality]);
    }

    /**
     * @inheritdoc
     */
    protected function delete($attribute, $old = false)
    {
        $profiles = array_keys($this->thumbs);
        foreach ($profiles as $profile) {
            $path = $this->getThumbUploadPath($attribute, $profile, $old);
            $this->deleteFile($path);
        }
        parent::delete($attribute, $old);
    }

    /**
     * Remove image and all thumbs
     *
     * @param       $attribute
     * @param false $old
     */
    public function deleteImage($attribute, $old = false)
    {
        $this->delete($attribute, $old);
        $this->owner->updateAttributes([
            $attribute => ''
        ]);
    }
}
