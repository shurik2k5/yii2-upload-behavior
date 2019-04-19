<?php

namespace mohorev\file;

use Closure;
use Yii;
use yii\base\Behavior;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\httpclient\Client;
use yii\web\UploadedFile;

/**
 * UploadBehavior automatically uploads file and fills the specified attribute
 * with a value of the name of the uploaded file.
 *
 * To use UploadBehavior, insert the following code to your ActiveRecord class:
 *
 * ```php
 * use mohorev\file\UploadBehavior;
 *
 * function behaviors()
 * {
 *     return [
 *         [
 *             'class' => UploadBehavior::class,
 *             'attribute' => 'file',
 *             'scenarios' => ['insert', 'update'],
 *             'path' => '@webroot/upload/{id}',
 *             'url' => '@web/upload/{id}',
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Alexander Mohorev <dev.mohorev@gmail.com>
 * @author Alexey Samoylov <alexey.samoylov@gmail.com>
 */
class UploadBehavior extends Behavior
{
    /**
     * @event Event an event that is triggered after a file is uploaded.
     */
    const EVENT_AFTER_UPLOAD = 'afterUpload';

    /**
     * @var string the attribute which holds the attachment.
     */
    public $attribute;
    /**
     * @var array the scenarios in which the behavior will be triggered
     */
    public $scenarios = [];
    /**
     * @var string|callable|array Base path or path alias to the directory in which to save files,
     * or callable for setting up your custom path generation logic.
     * If $path is callable, callback signature should be as follow and return a string:
     *
     * ```php
     * function (\yii\db\ActiveRecord $model)
     * {
     *     // do something...
     *     return $string;
     * }
     * ```
     * If this property is set up as array, it should be, for example, like as follow ['\app\models\UserProfile', 'buildAvatarPath'],
     * where first element is class name, while second is its static method that should be called for path generation.
     *
     * Example:
     * ```php
     * public static function buildAvatarPath(\yii\db\ActiveRecord $model)
     * {
     *      $basePath = '@webroot/upload/avatars/';
     *      $suffix = implode('/', array_slice(str_split(md5($model->id), 2), 0, 2));
     *      return $basePath . $suffix;
     * }
     * ```
     */
    public $path;
    /**
     * @var string|callable|array Base URL or path alias for this file,
     * or callable for setting up your custom URL generation logic.
     * If $url is callable, callback signature should be as follow and return a string:
     *
     * ```php
     * function (\yii\db\ActiveRecord $model)
     * {
     *     // do something...
     *     return $string;
     * }
     * ```
     * If this property is set up as array, it should be, for example, like as follow ['\app\models\UserProfile', 'buildAvatarUrl'],
     * where first element is class name, while second is its static method that should be called for URL generation.
     *
     * Example:
     * ```php
     * public static function buildAvatarUrl(\yii\db\ActiveRecord $model)
     * {
     *      $baseUrl = '@web/upload/avatars/';
     *      $suffix = implode('/', array_slice(str_split(md5($model->id), 2), 0, 2));
     *      return $baseUrl . $suffix;
     * }
     * ```
     */
    public $url;
    /**
     * @var bool Getting file instance by name
     */
    public $instanceByName = false;
    /**
     * @var boolean|callable generate a new unique name for the file
     * set true or anonymous function takes the old filename and returns a new name.
     * @see self::generateFileName()
     */
    public $generateNewName = true;
    /**
     * @var boolean If `true` current attribute file will be deleted
     */
    public $unlinkOnSave = true;
    /**
     * @var boolean If `true` current attribute file will be deleted after model deletion.
     */
    public $unlinkOnDelete = true;
    /**
     * @var boolean $deleteTempFile whether to delete the temporary file after saving.
     */
    public $deleteTempFile = true;
    /**
     * @var boolean $deleteEmptyDir whether to delete the empty directory after model deletion.
     */
    public $deleteEmptyDir = true;
    /**
     * @var bool restore old value after fail attribute validation
     */
    public $restoreValueAfterFailValidation = true;
    /**
     * @var string temporary folder name
     */
    public $tempFolder = '@runtime/';
    /**
     * @var UploadedFile the uploaded file instance.
     */
    private $_file;
    /**
     * @var import flag for not generate new filename on import
     */
    private $_import;
    /**
     * @var temporary filename
     */
    private $_temp_file_path;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if ($this->attribute === null) {
            throw new InvalidConfigException('The "attribute" property must be set.');
        }
        if ($this->path === null) {
            throw new InvalidConfigException('The "path" property must be set.');
        }
        if ($this->url === null) {
            throw new InvalidConfigException('The "url" property must be set.');
        }
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            BaseActiveRecord::EVENT_AFTER_VALIDATE => 'afterValidate',
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * This method is invoked before validation starts.
     */
    public function beforeValidate()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (in_array($model->scenario, $this->scenarios)) {
            if (($file = $model->getAttribute($this->attribute)) instanceof UploadedFile) {
                $this->_file = $file;
            } else {
                if ($this->instanceByName === true) {
                    $this->_file = UploadedFile::getInstanceByName($this->attribute);
                } else {
                    $this->_file = UploadedFile::getInstance($model, $this->attribute);
                }
            }
            if ($this->_file instanceof UploadedFile) {
                $this->_file->name = $this->getFileName($this->_file);
                $model->setAttribute($this->attribute, $this->_file);
            }
        }
    }

    /**
     * This method is called at the beginning of inserting or updating a record.
     */
    public function beforeSave()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (in_array($model->scenario, $this->scenarios)) {
            if ($this->_file instanceof UploadedFile) {
                if (!$model->getIsNewRecord() && $model->isAttributeChanged($this->attribute)) {
                    if ($this->unlinkOnSave === true) {
                        $this->delete($this->attribute, true);
                    }
                }
                $model->setAttribute($this->attribute, $this->_file->name);
            } elseif (!$this->_import) {
                // Protect attribute
                unset($model->{$this->attribute});
            }
        } else {
            if (!$model->getIsNewRecord() && $model->isAttributeChanged($this->attribute)) {
                if ($this->unlinkOnSave === true) {
                    $this->delete($this->attribute, true);
                }
            }
        }
    }

    /**
     * This method is called at the end of inserting or updating a record.
     * @throws \yii\base\Exception
     */
    public function afterSave()
    {
        if ($this->_file instanceof UploadedFile) {
            $path = $this->getUploadPath($this->attribute);
            if (is_string($path) && FileHelper::createDirectory(dirname($path))) {
                $this->save($this->_file, $path);
                $this->deleteTempFile();
                $this->afterUpload();
            } else {
                throw new InvalidArgumentException(
                    "Directory specified in 'path' attribute doesn't exist or cannot be created."
                );
            }
        }
    }

    /**
     * This method is invoked after deleting a record.
     */
    public function afterDelete()
    {
        $attribute = $this->attribute;
        if ($this->unlinkOnDelete && $attribute) {
            $this->delete($attribute);
        }
    }

    /**
     * Returns file path for the attribute.
     * @param string $attribute
     * @param boolean $old
     * @return string|null the file path.
     * @throws \yii\base\InvalidConfigException
     */
    public function getUploadPath($attribute, $old = false)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        $path = $this->resolvePath($this->path);
        $fileName = ($old === true) ? $model->getOldAttribute($attribute) : $model->$attribute;

        return $fileName ? Yii::getAlias($path . '/' . $fileName) : null;
    }

    /**
     * Returns file url for the attribute.
     * @param string $attribute
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getUploadUrl($attribute)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        $url = $this->resolvePath($this->url);
        $fileName = $model->getOldAttribute($attribute);

        return $fileName ? Yii::getAlias($url . '/' . $fileName) : null;
    }

    /**
     * Returns the UploadedFile instance.
     * @return UploadedFile
     */
    protected function getUploadedFile()
    {
        return $this->_file;
    }

    /**
     * Replaces all placeholders in path variable with corresponding values.
     */
    protected function resolvePath($path)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (is_string($path)) {
            return preg_replace_callback('/{([^}]+)}/', function ($matches) use ($model) {
                $name = $matches[1];
                $attribute = ArrayHelper::getValue($model, $name);
                if (is_string($attribute) || is_numeric($attribute)) {
                    return $attribute;
                } else {
                    return $matches[0];
                }
            }, $path);
        } elseif (is_callable($path) || is_array($path)) {
            return call_user_func($path, $model);
        } else {
            throw new InvalidArgumentException(
                '$path argument must be a string, array or callable: ' . gettype($path) . ' given.'
            );
        }
    }

    /**
     * Saves the uploaded file.
     * @param UploadedFile $file the uploaded file instance
     * @param string $path the file path used to save the uploaded file
     * @return boolean true whether the file is saved successfully
     */
    protected function save($file, $path)
    {
        return $file->saveAs($path, $this->deleteTempFile);
    }

    /**
     * Deletes old file.
     * @param string $attribute
     * @param boolean $old
     */
    protected function delete($attribute, $old = false)
    {
        $path = $this->getUploadPath($attribute, $old);

        $this->deleteFile($path);

        if ($this->deleteEmptyDir) {
            $dir = dirname($path);
            if (is_dir($dir) && count(scandir($dir)) == 2) {
                rmdir($dir);
            }
        }
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    protected function getFileName($file)
    {
        if ($this->generateNewName && !$this->_import) {
            return $this->generateNewName instanceof Closure
                ? call_user_func($this->generateNewName, $file)
                : $this->generateFileName($file);
        } else {
            return $this->sanitize($file->name);
        }
    }

    /**
     * Replaces characters in strings that are illegal/unsafe for filename.
     *
     * #my*  unsaf<e>&file:name?".png
     *
     * @param string $filename the source filename to be "sanitized"
     * @return boolean string the sanitized filename
     */
    public static function sanitize($filename)
    {
        return str_replace([' ', '"', '\'', '&', '/', '\\', '?', '#'], '-', $filename);
    }

    /**
     * Generates random filename.
     * @param UploadedFile $file
     * @return string
     */
    protected function generateFileName($file)
    {
        return uniqid() . '.' . $file->extension;
    }

    /**
     * This method is invoked after uploading a file.
     * The default implementation raises the [[EVENT_AFTER_UPLOAD]] event.
     * You may override this method to do postprocessing after the file is uploaded.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    protected function afterUpload()
    {
        $this->owner->trigger(self::EVENT_AFTER_UPLOAD);
    }

    /**
     * Delete file from path
     * @param string $path
     */
    protected function deleteFile($path)
    {
        if (is_file($path)) {
            unlink($path);
        }
        return;
    }

    /**
     * Set old attribute value if has attribute validation error
     */
    public function afterValidate()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;

        if ($this->restoreValueAfterFailValidation && $model->hasErrors($this->attribute))
            $model->setAttribute($this->attribute, $model->getOldAttribute($this->attribute));

        return;
    }

    /**
     * Upload file from url
     *
     * @param $attribute string name of attribute with attached UploadBehavior
     * @param $url string
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     * @throws \yii\httpclient\Exception
     */
    public function uploadFromUrl($attribute, $url) {

        $this->_import = true;

        $client = new Client();
        $response = $client->createRequest()
            ->setUrl($url)
            ->setMethod('GET')
            ->send();
        $contentType = $response->getHeaders()->get('Content-Type');

        if ($response->isOk) {

            $fileContent = $response->content;
            $this->setAttributeFile($attribute, $url, $fileContent);

        }
        else {
            throw new InvalidArgumentException('url $url not valid');
        }
    }

    /**
     * Upload file from local storage
     *
     * @param $attribute string name of attribute with attached UploadBehavior
     * @param $filename
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    public function uploadFromFile($attribute, $filename) {

        $this->_import = true;

        $file_path = \Yii::getAlias($filename);

        if (file_exists($file_path)) {
            $this->setAttributeFile($attribute, $file_path);
        }
        else {
            throw new InvalidArgumentException('file $filename not exist');
        }

    }

    /**
     * @param $attribute
     * @param $url
     * @param string $fileContent
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    protected function setAttributeFile($attribute, $filePath, $fileContent = null)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;

        $old_value = $model->getAttribute($attribute);

        $temp_filename = uniqid();
        $temp_file_path = \Yii::getAlias($this->tempFolder) . $temp_filename;

        try {
            if ($fileContent === null) {
                @copy($filePath, $temp_file_path);
            } else {
                @file_put_contents($temp_file_path, $fileContent);
            }

            $this->_temp_file_path = $temp_file_path;

            $pathinfo = pathinfo($filePath);

            //check extension by mime type
            $mime = FileHelper::getMimeType($temp_file_path);
            $extension = FileHelper::getExtensionsByMimeType($mime);

            //compare with pathinfo values
            if (in_array($pathinfo['extension'], $extension)) {
                $extension = $pathinfo['extension'];
            } else {
                $extension = $extension[0];
            }

            //get full filename
            if ($this->generateNewName) {
                $full_filename = basename($temp_filename) . '.' . $extension;
            } else {
                $full_filename = $pathinfo['filename'] . '.' . $extension;
            }

            //for validation
            $upload = new UploadedFile();
            $upload->tempName = $temp_file_path;
            $upload->name = basename($full_filename);

            $model->setAttribute($attribute, $upload);
            //check validation rules in model
            if ($result = $model->validate($attribute)) {

                $this->_file = $upload;

                $file_path = $this->getUploadPath($attribute);
                //copy file to uploadpath folder
                if (is_string($file_path) && FileHelper::createDirectory(dirname($file_path))) {
                    @copy($temp_file_path, $file_path);
                }
            } else {
                $model->setAttribute($attribute, $old_value);
                $this->deleteTempFile();
            }
        } catch (\Exception $e) {
            $this->deleteTempFile();
        }
    }

    /**
     * remove temp file
     */
    protected function deleteTempFile()
    {
        if ($this->_temp_file_path !== null) {
            $this->deleteFile($this->_temp_file_path);
            $this->_temp_file_path = null;
        }
    }
}
