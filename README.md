[![Build Status](https://api.travis-ci.org/shurik2k5/yii2-upload-behavior.svg?branch=master)](https://travis-ci.org/shurik2k5/yii2-upload-behavior)
[![Total Downloads](https://img.shields.io/packagist/dt/shurik2k5/yii2-upload-behavior.svg)](https://packagist.org/packages/shurik2k5/yii2-upload-behavior)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/shurik2k5/yii2-upload-behavior/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/shurik2k5/yii2-upload-behavior/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/shurik2k5/yii2-upload-behavior/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/shurik2k5/yii2-upload-behavior/?branch=master)

Upload behavior for Yii 2
===========================

This behavior automatically uploads file and fills the specified attribute with a value of the name of the uploaded file.

In this behaviour added ability to load file from URL and local files, and attach behavior for two or more attributes.

This is an enhanced version of https://github.com/mohorev/yii2-upload-behavior/

Installation
------------

The preferred way to install this extension via [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist shurik2k5/yii2-upload-behavior "*"
```

or add this code line to the `require` section of your `composer.json` file:

```json
"shurik2k5/yii2-upload-behavior": "*"
```

Usage
-----

### Upload file from input forms

Attach the behavior in your model:

```php
class Document extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['file', 'file', 'extensions' => 'doc, docx, pdf', 'on' => ['insert', 'update']],
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(Category::class, [ 'id' => 'id_category' ]);
    }

    /**
     * @inheritdoc
     */
    function behaviors()
    {
        return [
            [
                'class' => \mohorev\file\UploadBehavior::class,
                'attribute' => 'file',
                'scenarios' => ['insert', 'update'],
                'path' => '@webroot/upload/docs/{category.id}',
                'url' => '@web/upload/docs/{category.id}',
            ],
        ];
    }
}
```

Set model scenario in controller action:

```php
class Controller extends Controller
{
    public function actionCreate($id)
    {
        $model = $this->findModel($id);
        $model->setScenario('insert'); // Note! Set upload behavior scenario.
        
        ...
        ...
    }
}

```

Example view file:

```php
<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
    <?= $form->field($model, 'image')->fileInput() ?>
    <div class="form-group">
        <?= Html::submitButton('Submit', ['class' => 'btn btn-primary']) ?>
    </div>
<?php ActiveForm::end(); ?>
```
### Upload file from url
To load file from url
```php
$model = new Document();
$model->setScenario('update'); //Use scenarion for validation and load file
$model->uploadFromUrl('file', 'https://i.ytimg.com/vi/yfJbKba5xYM/maxresdefault.jpg');
$model->save();
```
### Upload file from file
To load file from local file
```php
$model = new Document();
$model->setScenario('update'); //Use scenarion for validation and load file
$model->uploadFromFile('file', \Yii::getAlias('@webroot/images/02.jpg'));
$model->save();
```

### Get path in you application 
Get upload url
```php
$model->getUploadUrl('file');
```

Get upload path
```php
$model->getUploadPath('file');
```

### Upload image and create thumbnails

Thumbnails processing requires [yiisoft/yii2-imagine](https://github.com/yiisoft/yii2-imagine) to be installed.

Attach the behavior in your model:

```php
class User extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['image', 'image', 'extensions' => 'jpg, jpeg, gif, png', 'on' => ['insert', 'update']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => \mohorev\file\UploadImageBehavior::class,
                'attribute' => 'image',
                'scenarios' => ['insert', 'update'],
                'placeholder' => '@app/modules/user/assets/images/userpic.jpg',
                'path' => '@webroot/upload/user/{id}',
                'url' => '@web/upload/user/{id}',
                //if need create all thumbs profiles on image upload
                'createThumbsOnSave' => true,
                //if need create thumb for one profile only on request by getThumbUploadUrl() method
                'createThumbsOnRequest' => true,
                //if you want to remove original upload file after images thumbs was generated
                'deleteOriginalFile' => true,
                'thumbs' => [
                    'thumb' => ['width' => 400, 'quality' => 90],
                    'preview' => ['width' => 200, 'height' => 200],
                    'news_thumb' => ['width' => 200, 'height' => 200, 'bg_color' => '000'],
                ],
            ],
        ];
    }
}
```

### Remove upload image and all thumbs
```php
//remove image and all thumbs from 'photo' attribute
$model->deleteImage('photo');

```

## Flexible configuration for path and URL generation 

More flexible configuration for `path` and/or `url` behavior properties is that to use 
callbacks or array for defining `path` or `url` generation logic.

#### I. Via callbacks

```php
/**
 * @inheritdoc
 */
public function behaviors()
{
    return [
        [
            'class' => \mohorev\file\UploadImageBehavior::class,
            'attribute' => 'image',
            'scenarios' => ['insert', 'update'],
            'placeholder' => '@app/modules/user/assets/images/userpic.jpg',
            'path' => function ($model) {
                /** @var \app\models\UserProfile $model */
                $basePath = '@webroot/upload/profiles/';
                $path = implode('/', array_slice(str_split(md5($model->id), 2), 0, 2));
                return $basePath . $path;
            },
            'url' => function ($model) {
                /** @var \app\models\UserProfile $model */
                $baseUrl = '@web/upload/profiles/';
                $path = implode('/', array_slice(str_split(md5($model->id), 2), 0, 2));
                return $baseUrl . $path;
            },
            'thumbs' => [
                'thumb' => ['width' => 400, 'quality' => 90],
                'preview' => ['width' => 200, 'height' => 200],
                'news_thumb' => ['width' => 200, 'height' => 200, 'bg_color' => '000'],
            ],
        ],
    ];
}
```

#### II. Via array configuration by defining class and its static methods for path/URL generation

```php
/**
 * @inheritdoc
 */
public function behaviors()
{
    return [
        [
            'class' => \mohorev\file\UploadImageBehavior::class,
            'attribute' => 'image',
            'scenarios' => ['insert', 'update'],
            'placeholder' => '@app/modules/user/assets/images/userpic.jpg',
            'path' => [UserProfile::class, 'buildAvatarPath'],
            'url' => [UserProfile::class, 'buildAvatarUrl'],
            'thumbs' => [
                'thumb' => ['width' => 400, 'quality' => 90],
                'preview' => ['width' => 200, 'height' => 200],
                'news_thumb' => ['width' => 200, 'height' => 200, 'bg_color' => '000'],
            ],
        ],
    ];
}

/**
 * Define two static methos in your model for path and URL generation
 */ 
/**
 * @param \app\models\UserProfile|\yii\db\ActiveRecord $profile
 * @return string
 */
public static function buildAvatarPath(UserProfile $model)
{
    $basePath = '@webroot/upload/profiles/';
    $path = implode('/', array_slice(str_split(md5($model->id), 2), 0, 2));

    return $basePath . $path;
}

/**
 * @param \app\models\UserProfile|\yii\db\ActiveRecord $profile
 * @return string
 */
public static function buildAvatarUrl(UserProfile $model)
{
    $baseUrl = '@web/upload/profiles/';
    $path = implode('/', array_slice(str_split(md5($model->id), 2), 0, 2));

    return $baseUrl . $path;
}
```  

Example view file:

```php
<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
    <div class="form-group">
        <div class="row">
            <div class="col-lg-6">
                <!-- Original image -->
                <?= Html::img($model->getUploadUrl('image'), ['class' => 'img-thumbnail']) ?>
            </div>
            <div class="col-lg-4">
                <!-- Thumb 1 (thumb profile) -->
                <?= Html::img($model->getThumbUploadUrl('image'), ['class' => 'img-thumbnail']) ?>
            </div>
            <div class="col-lg-2">
                <!-- Thumb 2 (preview profile) -->
                <?= Html::img($model->getThumbUploadUrl('image', 'preview'), ['class' => 'img-thumbnail']) ?>
            </div>
        </div>
    </div>
    <?= $form->field($model, 'image')->fileInput(['accept' => 'image/*']) ?>
    <div class="form-group">
        <?= Html::submitButton('Submit', ['class' => 'btn btn-primary']) ?>
    </div>
<?php ActiveForm::end(); ?>
```

Behavior Options
-------

* attribute - The attribute which holds the attachment
* scenarios - The scenarios in which the behavior will be triggered
* instanceByName - Getting file instance by name, If you use UploadBehavior in `RESTfull` application and you do not need a prefix of the model name, set the property `instanceByName = false`, default value is `false`
* path - the base path or path alias to the directory in which to save files.
* url - the base URL or path alias for this file
* generateNewName - Set true or anonymous function takes the old filename and returns a new name, default value is `true`
* unlinkOnSave - If `true` current attribute file will be deleted, default value is `true`
* unlinkOnDelete - If `true` current attribute file will be deleted after model deletion.
* deleteEmptyDir - If `true` the **empty** directory will be deleted after model deletion, default value is `true`.

UploadImageBehavior additional Options
-------

* createThumbsOnSave - If `true` create all thumbs profiles on image upload
* createThumbsOnRequest - If `true` create thumb only for profile request by `getThumbUploadUrl('attribute', 'profile_name)` method. If `true` recommend to set `createThumbsOnSave` to `false`
* deleteOriginalFile - If `true` the **original upload image** will be deleted after images thumbs was generated, default value is `false`.\
**Attention** don't use with **createThumbsOnRequest** options, because thumbs generate on request (NOT after upload image) and after first profile thumb generation original file will be deleted!

### Attention!

It is prefered to use immutable placeholder in `url` and `path` options, other words try don't use related attributes that can be changed. There's bad practice. For example:

```php
class Track extends ActiveRecord
{
    public function getArtist()
    {
        return $this->hasOne(Artist::class, [ 'id' => 'id_artist' ]);
    }

    public function behaviors()
    {
        return [
            [
                'class' => \mohorev\file\UploadBehavior::class,
                'attribute' => 'image',
                'scenarios' => ['default'],
                'path' => '@webroot/uploads/{artist.slug}',
                'url' => '@web/uploads/{artist.slug}',
            ],
        ];
    }
}
```

If related model attribute `slug` will change, you must change folders' names too, otherwise behavior will works not correctly.

Authors
-----
* Alexander Mohorev <dev.mohorev@gmail.com>
* Alexey Samoylov <alexey.samoylov@gmail.com>
* Aleksandr Pogorelov <shurik2k5@gmail.com>
