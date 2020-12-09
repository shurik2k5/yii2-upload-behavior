<?php

namespace tests;

use tests\models\Gallery;
use tests\models\User;
use yii\web\UploadedFile;

class UploadImageBehaviorTest extends DatabaseTestCase
{
    public function testFindUsers()
    {
        $data = User::find()->asArray()->all();
        $this->assertEquals(require(__DIR__ . '/data/test-find-users.php'), $data);
    }

    public function testFindUser()
    {
        $user = User::findOne(1);
        $this->assertEquals('admin', $user->nickname);
        $this->assertEquals('image-1.jpg', $user->image);
    }

    public function testGetFileInstance()
    {
        $file = UploadedFile::getInstanceByName('User[image]');
        $this->assertTrue($file instanceof UploadedFile);
    }

    public function testCreateUser()
    {
        $user = new User([
            'nickname' => 'Alex',
        ]);
        $user->setScenario('insert');

        $this->assertTrue($user->save());

        $path = $user->getUploadPath('image');
        $this->assertTrue(is_file($path));
        $this->assertEquals(sha1_file($path), sha1_file(__DIR__ . '/data/test-image.jpg'));
    }

    public function testResizeUser()
    {
        $user = User::findOne(1);
        $user->setScenario('update');

        $this->assertTrue($user->save());

        //request image by url
        $thumbUrl = $user->getThumbUploadUrl('image', 'thumb');
        $thumbPath = $user->getThumbUploadPath('image', 'thumb');

        $thumbInfo = getimagesize($thumbPath);
        $this->assertEquals(400, $thumbInfo[0]);
        $this->assertEquals(300, $thumbInfo[1]);

        //request image by url
        $previewUrl = $user->getThumbUploadUrl('image', 'preview');
        $previewPath = $user->getThumbUploadPath('image', 'preview');
        $previewInfo = getimagesize($previewPath);
        $this->assertEquals(200, $previewInfo[0]);
        $this->assertEquals(200, $previewInfo[1]);
    }

    public function testCreateUserImagesByRequestUrl()
    {
        $user = new User([
            'nickname' => 'Alex',
            'id' => 4,
        ]);
        $user->setScenario('insert');

        $this->assertTrue($user->save());

        $this->assertFalse(file_exists(__DIR__ . '/upload/user/' . $user->id . '/preview-test-image.jpg'));
        //request image by url
        $previewPath = $user->getThumbUploadUrl('image', 'preview');
        $this->assertTrue(file_exists(__DIR__ . '/upload/user/' . $user->id . '/preview-test-image.jpg'));
    }

    public function testGetPlaceholderOnNotExistAttribute() {
        $user = User::findOne(1);

        $image = $user->getThumbUploadUrl('not_exist_attribute', 'thumb');
        $this->assertContains('thumb-test-image.jpg', $image);
    }

    public function testFindGalleries()
    {
        $data = Gallery::find()->asArray()->all();
        $this->assertEquals(require(__DIR__ . '/data/test-find-galleries.php'), $data);
    }

    public function testFindGallery()
    {
        $gallery = Gallery::findOne(1);
        $this->assertEquals('image-1.jpg', $gallery->image1);
        $this->assertEquals('image-2.jpg', $gallery->image2);
    }

    public function testGetUploadWithMultiplyBehaviorsAttachedOne()
    {
        $gallery = Gallery::findOne(3);

        $gallery->setScenario('update');

        $this->assertTrue($gallery->save());

        $path1 = $gallery->getUploadPath('image1');
        $this->assertNull($path1);

        $url1 = $gallery->getThumbUploadUrl('image1', 'thumb');
        $this->assertContains('assets', $url1);

        $path2 = $gallery->getUploadPath('image2');
        $this->assertTrue(is_file($path2));
        $this->assertEquals(sha1_file($path2), sha1_file(__DIR__ . '/data/test-image.jpg'));

        $url2 = $gallery->getThumbUploadUrl('image2', 'thumb');
        $this->assertContains('upload/gallery', $url2);
    }

    public function testGetUploadWithMultiplyBehaviorsAttachedTwo()
    {
        $gallery = Gallery::findOne(4);

        $gallery->setScenario('update');

        $_FILES['Gallery[image1]'] = $_FILES['Gallery[image2]'];
        unset($_FILES['Gallery[image2]']);

        $this->assertTrue($gallery->save());

        $url1 = $gallery->getThumbUploadUrl('image1', 'thumb');
        $this->assertContains('upload/gallery', $url1);

        $path1 = $gallery->getUploadPath('image1');
        $this->assertTrue(is_file($path1));
        $this->assertEquals(sha1_file($path1), sha1_file(__DIR__ . '/data/test-image.jpg'));

        $path2 = $gallery->getUploadPath('image2');
        $this->assertNull($path2);

        $url2 = $gallery->getThumbUploadUrl('image2', 'thumb');
        $this->assertContains('assets', $url2);
    }

    public function testDeleteImage()
    {
        $gallery = Gallery::findOne(4);

        $gallery->setScenario('update');

        $this->assertTrue($gallery->save());

        $path = $gallery->getUploadPath('image2');
        $this->assertFileExists($path);

        $thumb = $gallery->getThumbUploadPath('image2', 'thumb');
        $preview = $gallery->getThumbUploadPath('image2', 'preview');

        $this->assertFileExists($thumb);
        $this->assertFileExists($preview);

        $gallery->deleteImage('image2');

        $this->assertEquals('', $gallery->image2);

        $this->assertFileNotExists($path);
        $this->assertFileNotExists($thumb);
        $this->assertFileNotExists($preview);
    }

    /**
     * @inheritdoc
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $_FILES = [
            'User[image]' => [
                'name' => 'test-image.jpg',
                'type' => 'image/jpeg',
                'size' => 74463,
                'tmp_name' => __DIR__ . '/fixtures/test-image.jpg',
                'error' => 0,
            ],
            'Gallery[image2]' => [
                'name' => 'test-image.jpg',
                'type' => 'image/jpeg',
                'size' => 74463,
                'tmp_name' => __DIR__ . '/fixtures/test-image.jpg',
                'error' => 0,
            ],
        ];
    }
}
