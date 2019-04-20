<?php

namespace tests\models;

use mohorev\file\UploadImageBehavior;
use yii\db\ActiveRecord;

/**
 * Class User
 */
class User extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user';
    }

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
                'class' => UploadImageBehavior::class,
                'attribute' => 'image',
                'scenarios' => ['insert', 'update'],
                'path' => '@webroot/upload/user/{id}',
                'url' => '@web/upload/user/{id}',
                'placeholder' => '@tests/data/test-image.jpg',
                'createThumbsOnRequest' => true,
                'createThumbsOnSave' => false,
                'generateNewName' => false,
                'thumbs' => [
                    'thumb' => ['width' => 400, 'quality' => 90],
                    'preview' => ['width' => 200, 'height' => 200],
                ],
            ],
        ];
    }
}
