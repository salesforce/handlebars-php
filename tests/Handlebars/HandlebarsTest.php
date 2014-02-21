<?php

/**
 * Class AutoloaderTest
 */
class HandlebarsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test handlebars autoloader
     *
     * @return void
     */
    public function testAutoLoad()
    {
        Handlebars\Autoloader::register(realpath(__DIR__ . '/../fixture/'));

        $this->assertTrue(class_exists('Handlebars\\Test'));
        $this->assertTrue(class_exists('Handlebars\\Example\\Test'));
    }

    /**
     * Test basic tags
     *
     * @param string $src    handlebars source
     * @param array  $data   data
     * @param string $result expected data
     *
     * @dataProvider simpleTagdataProvider
     *
     * @return void
     */
    public function testBasicTags($src, $data, $result)
    {
        $loader = new \Handlebars\Loader\StringLoader();
        $engine = new \Handlebars\Handlebars(array('loader' => $loader));
        $this->assertEquals($result, $engine->render($src, $data));
    }

    /**
     * Simple tag provider
     *
     * @return array
     */
    public function simpleTagdataProvider()
    {
        return array(
            array(
                '{{! This is comment}}',
                array(),
                ''
            ),
            array(
                '{{data}}',
                array('data' => 'result'),
                'result'
            ),
            array(
                '{{data.key}}',
                array('data' => array('key' => 'result')),
                'result'
            ),
        );
    }


    /**
     * Test helpers (internal helpers)
     *
     * @param string $src    handlebars source
     * @param array  $data   data
     * @param string $result expected data
     *
     * @dataProvider internalHelpersdataProvider
     *
     * @return void
     */
    public function testSimpleHelpers($src, $data, $result)
    {
        $loader = new \Handlebars\Loader\StringLoader();
        $helpers = new \Handlebars\Helpers();
        $engine = new \Handlebars\Handlebars(array('loader' => $loader, 'helpers' => $helpers));

        $this->assertEquals($result, $engine->render($src, $data));
    }

    /**
     * Simple helpers provider
     *
     * @return array
     */
    public function internalHelpersdataProvider()
    {
        return [
            [
                '{{#if data}}Yes{{/if}}',
                ['data' => true],
                'Yes'
            ],
            [
                '{{#if data}}Yes{{/if}}',
                ['data' => false],
                ''
            ],
            [
                '{{#unless data}}OK{{/unless}}',
                ['data' => false],
                'OK'
            ],
            [
                '{{#unless data}}OK {{else}}I believe{{/unless}}',
                ['data' => true],
                'I believe'
            ],
            [
                '{{#with data}}{{key}}{{/with}}',
                ['data' => ['key' => 'result']],
                'result'
            ],
            [
                '{{#each data}}{{this}}{{/each}}',
                ['data' => [1, 2, 3, 4]],
                '1234'
            ],
            [
                '{{#each data[0:2]}}{{this}}{{/each}}',
                ['data' => [1, 2, 3, 4]],
                '12'
            ],
            [
                '{{#each data[1:2]}}{{this}}{{/each}}',
                ['data' => [1, 2, 3, 4]],
                '23'
            ],
            [
                '{{#upper data}}',
                ['data' => "hello"],
                'HELLO'
            ],
            [
                '{{#lower data}}',
                ['data' => "HELlO"],
                'hello'
            ],
            [
                '{{#capitalize data}}',
                ['data' => "hello"],
                'Hello'
            ],
            [
                '{{#capitalize_words data}}',
                ['data' => "hello world"],
                'Hello World'
            ],
            [
                '{{#reverse data}}',
                ['data' => "hello"],
                'olleh'
            ],
            [
                "{{#inflect count 'album' 'albums' }}",
                ["count" => 1],
                'album'
            ],
            [
                "{{#inflect count 'album' 'albums' }}",
                ["count" => 10],
                'albums'
            ],
            [
                "{{#inflect count '%d album' '%d albums' }}",
                ["count" => 1],
                '1 album'
            ],
            [
                "{{#inflect count '%d album' '%d albums' }}",
                ["count" => 10],
                '10 albums'
            ],
            [
                "{{#default data 'OK' }}",
                ["data" => "hello"],
                'hello'
            ],
            [
                "{{#default data 'OK' }}",
                [],
                'OK'
            ],
            [
                "{{#truncate data 8 '...'}}",
                ["data" => "Hello World! How are you?"],
                'Hello Wo...'
            ],
            [
                "{{#raw}}I'm raw {{data}}{{/raw}}",
                ["data" => "raw to be included, but won't :)"],
                "I'm raw {{data}}"
            ],
            [
                "{{#repeat 3}}Yes {{/repeat}}",
                [],
                "Yes Yes Yes "
            ],
            [
                "{{#repeat 4}}Nice {{data}} {{/repeat}}",
                ["data" => "Daddy!"],
                "Nice Daddy! Nice Daddy! Nice Daddy! Nice Daddy! "
            ],
            [
                "{{#define test}}I'm Defined and Invoked{{/define}}{{#invoke test}}",
                [],
                "I'm Defined and Invoked"
            ],
        ];
    }

    /**
     * Management helpers
     */
    public function testHelpersManagement()
    {
        $helpers = new \Handlebars\Helpers(array('test' => function () {
        }), false);
        $engine = new \Handlebars\Handlebars(array('helpers' => $helpers));
        $this->assertTrue(is_callable($engine->getHelper('test')));
        $this->assertTrue($engine->hasHelper('test'));
        $engine->removeHelper('test');
        $this->assertFalse($engine->hasHelper('test'));
    }

    /**
     * Custom helper test
     */
    public function testCustomHelper()
    {
        $loader = new \Handlebars\Loader\StringLoader();
        $engine = new \Handlebars\Handlebars(array('loader' => $loader));
        $engine->addHelper('test', function () {
            return 'Test helper is called';
        });
        $this->assertEquals('Test helper is called', $engine->render('{{#test}}', []));
    }

    /**
     * @param $dir
     *
     * @return bool
     */
    private function delTree($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
        }

        return rmdir($dir);
    }

    /**
     * Its not a good test :) but ok
     */
    public function testCacheSystem()
    {
        $path = sys_get_temp_dir() . '/__cache__handlebars';

        @$this->delTree($path);

        $dummy = new \Handlebars\Cache\Disk($path);
        $engine = new \Handlebars\Handlebars(array('cache' => $dummy));
        $this->assertEquals(0, count(glob($path . '/*')));
        $engine->render('test', array());
        $this->assertEquals(1, count(glob($path . '/*')));
    }
}