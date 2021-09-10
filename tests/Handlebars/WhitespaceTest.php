<?php

require_once 'MapLoader.php';

use Handlebars\Handlebars;
use Handlebars\Helpers;
use Handlebars\Loader\StringLoader;

class WhitespaceTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param bool $enabled
     * @param string $template
     * @param array $data
     * @param array $partials
     * @param string $expected
     * @dataProvider dataWhitespaceControl
     */
    public function testWhitespaceControl($enabled, $template, $data, $partials, $expected)
    {
        $helpers = new Helpers();
        $engine = new Handlebars(array(
            'loader' => new StringLoader(),
            'partials_loader' => new MapLoader($partials),
            'helpers' => $helpers,
            'enableWhitespaceControl'=> $enabled,
        ));

        $this->assertEquals($expected, $engine->render($template, $data));
    }

    public function dataWhitespaceControl()
    {
        $data = [
            'foo' => 'bar<',
            '~foo~' => '~bar~',
            '~foo' => '~bar',
            'foo~' => 'bar~',
            '~else~' => '~caz~',
            '~else' => '~caz',
            'else~' => 'caz~',
        ];

        $cases = [
            [
                'template' => ' {{foo}} ',
                'data' => $data,
                'enabled' => ' bar&lt; ',
                'disabled' => ' bar&lt; ',
            ], [
                'template' => ' {{~foo~}} ',
                'data' => $data,
                'enabled' => 'bar&lt;',
                'disabled' => ' ~bar~ ', // should grab the value out of the data
            ], [
                'template' => ' {{~foo}} ',
                'data' => $data,
                'enabled' => 'bar&lt; ',
                'disabled' => ' ~bar ',
            ], [
                'template' => ' {{foo~}} ',
                'data' => $data,
                'enabled' => ' bar&lt;',
                'disabled' => ' bar~ ',
            ], [
                'template' => ' {{~#if foo~}} bar {{~/if~}} ',
                'data' => $data,
                'enabled' => 'bar',
                'disabled' => '  bar  '
            ], [
                'template' => ' {{#if foo~}} bar {{/if~}} ',
                'data' => $data,
                'enabled' => ' bar ',
                'disabled' => '  bar  '
            ], [
                'template' => ' {{~#if foo}} bar {{~/if}} ',
                'data' => $data,
                'enabled' => ' bar ',
                'disabled' => '  bar  '
            ], [
                'template' => '  {{#if foo}} bar {{/if}}  ',
                'data' => $data,
                'enabled' => '   bar   ',
                'disabled' => '   bar   '
            ], [
                'template' => " \n\n{{~#if foo~}} \n\nbar \n\n{{~/if~}}\n\n ",
                'data' => $data,
                'enabled' => "bar",
                'disabled' => " \n\n\nbar \n\n\n "
            ], [
                'template' => " a\n\n{{~#if foo~}} \n\nbar \n\n{{~/if~}}\n\na ",
                'data' => $data,
                'enabled' => ' abara ',
                'disabled' => " a\n\n\nbar \n\n\na "
            ], [
                'template' => '{{#if foo~}} bar {{~else~}} baz {{~/if}}',
                'data' => $data,
                'enabled' => 'bar',
                'disabled' => ' bar ~caz~ baz ' // {{~else~}} is evaluated as a variable
            ], [
                'template' => '{{#if foo~}} bar {{~else~}} baz {{~/if}}',
                'data' => [],
                'enabled' => 'baz',
                'disabled' => ''
            ], [
                'template' => 'foo {{~> dude~}} ',
                'data' => [],
                'partials' => [
                    'dude' => 'bar',
                    'dude~' => 'caz~'
                ],
                'enabled' => 'foobar',
                'disabled' => 'foo caz~ '
            ], [
                'template' => 'foo {{> dude~}} ',
                'data' => [],
                'partials' => [
                    'dude' => 'bar',
                    'dude~' => 'caz~'
                ],
                'enabled' => 'foo bar',
                'disabled' => 'foo caz~ '
            ], [
                'template' => 'foo {{> dude}} ',
                'data' => [],
                'partials' => [
                    'dude' => 'bar',
                ],
                'enabled' => 'foo bar ',
                'disabled' => 'foo bar '
            ], [
                'template' => "foo\n {{~> dude}} ",
                'data' => [],
                'partials' => [
                    'dude' => 'bar',
                ],
                'enabled' => 'foobar',
                'disabled' => "foo\nbar"
            ], [
                'template' => ' {{~foo~}} {{foo}} {{foo}} ',
                'data' => ['foo' => 'bar', '~foo~' => '~bar~'],
                'enabled' => 'barbar bar ',
                'disabled' => ' ~bar~ bar bar '
            ], [
                'template' => ' {{{~foo~}}} ',
                'data' => ['foo' => 'bar<', '~foo~' => '~bar<~'],
                'enabled' => 'bar<',
                'disabled' => ' ~bar<~ '
            ], [
                'template' => ' {{{~foo}}} ',
                'data' => ['foo' => 'bar<', '~foo' => '~bar<'],
                'enabled' => 'bar< ',
                'disabled' => ' ~bar< '
            ], [
                'template' => ' {{{foo~}}} ',
                'data' => ['foo' => 'bar<', 'foo~' => 'bar<~'],
                'enabled' => ' bar<',
                'disabled' => ' bar<~ '
            ]
        ];

        // Fan out each of the data cases into two cases: (1) for when the option is enabled and (2) when it's disabled.
        $allCases = [];
        foreach ($cases as $case) {
            // Test names in PHPUnit shouldn't be multiple lines so replace the newline characters
            $name = str_replace("\n", '\n', $case['template']);

            $partials = [];
            if (isset($case['partials'])) {
                $partials = $case['partials'];
            }

            // Add the case when the option is disabled
            $allCases['not enabled: ' . $name] = [
                false,
                $case['template'],
                $case['data'],
                $partials,
                $case['disabled'],
            ];

            // Add the case when the option is enabled
            $allCases['enabled: ' . $name] = [
                true,
                $case['template'],
                $case['data'],
                $partials,
                $case['enabled'],
            ];
        }
        return $allCases;
    }
}