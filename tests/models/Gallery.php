<?php

namespace tests\models;

use mohorev\file\UploadImageBehavior;
use yii\db\ActiveRecord;

/**
 * Class User
 */
class Gallery extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'gallery';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['image1', 'image2'], 'image', 'extensions' => 'jpg, jpeg, gif, png', 'on' => ['insert', 'update']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'image1' => [
                'class' => UploadImageBehavior::class,
                'attribute' => 'image1',
                'scenarios' => ['insert', 'update'],
                'path' => '@webroot/upload/gallery/{id}',
                'url' => '@web/upload/gallery/{id}',
                'placeholder' => '@tests/data/test-image.jpg',
                'createThumbsOnRequest' => true,
                'createThumbsOnSave' => false,
                'generateNewName' => false,
                'thumbs' => [
                    'thumb' => ['width' => 400, 'quality' => 90],
                    'preview' => ['width' => 200, 'height' => 200],
                ],
            ],
            'image2' => [
                'class' => UploadImageBehavior::class,
                'attribute' => 'image2',
                'scenarios' => ['insert', 'update'],
                'path' => '@webroot/upload/gallery/{id}',
                'url' => '@web/upload/gallery/{id}',
                'placeholder' => '@tests/data/test-image.jpg',
                'createThumbsOnRequest' => true,
                'createThumbsOnSave' => true,
                'generateNewName' => false,
                'thumbs' => [
                    'thumb' => ['width' => 400, 'quality' => 90],
                    'preview' => ['width' => 200, 'height' => 200],
                ],
            ],
        ];
    }
}
