<?php

require_once('bbcode.php');

function test($a, $b)
{
  static $test_num = 0;

  $test_num ++;
  echo "Test #", $test_num, ": ";

  try {
    $r = bbcode_to_html($a);

    if ($r !== $b)
    {
      echo "FAILED - items are not equal:\n= A =========\n$r\n= B =========\n$b\n=============\n";
      return 1;
    }
    else
    {
      echo "passed\n";
      return 0;
    }
  } catch (Exception $e) {
    echo 'ERROR - Caught exception: ',  $e->getMessage(), "\n= INPUT =====\n$a\n";
    return 1;
  }catch (Error $e){
    echo 'ERROR - Caught error: ',  $e->getMessage(), "\n= INPUT =====\n$a\n";
    return 1;
  }
}

// No formatting
test('test', 'test');
// HTML escaping
test('<b>test</b>', '&lt;b&gt;test&lt;/b&gt;');
// Unicode
test('Emoji is great 😀 Surrender to your Emoji overlords 💩', 'Emoji is great 😀 Surrender to your Emoji overlords 💩');
// Newlines
test("YOU\nSHALL\nNOT\nPASS!\r\n\r\n\nMoolti pass", "YOU\n<br>SHALL\n<br>NOT\n<br>PASS!\n\n<p>Moolti pass");

// Bold text
test('[b]test[/b]', '<b>test</b>');
// Fake tag
test('[spoiler]test[/spoiler]', '[spoiler]test[/spoiler]');
// Font adjustments
test('[font color=red]Red text [font size=200%]and BIG![/font][/font]  Now normal, and [font color=blue size=50%]now blue and small.[/font]', '<span style="color: red">Red text <span style="font-size: 200%">and BIG!</font></font>  Now normal, and <span style="font-size: 50%;color: blue">now blue and small.</font>');

// Preformatted text containing tags
test('Some BBCode tips: [code][u] - underline. [b] - bold.[/code]', 'Some BBCode tips: <pre>[u] - underline. [b] - bold.</pre>');

// URL tests: simple
test('Get the latest php-bbcode release here: [url]https://github.com/greg-kennedy/php-bbcode[/url]', 'Get the latest php-bbcode release here: <a href="https://github.com/greg-kennedy/php-bbcode">https://github.com/greg-kennedy/php-bbcode</a>');
// URL tests: complex
test('Get the latest php-bbcode release [url=https://github.com/greg-kennedy/php-bbcode]here[/url].', 'Get the latest php-bbcode release <a href="https://github.com/greg-kennedy/php-bbcode">here</a>.');
// URL encoding test
test('Do a Barrel Roll: [url]https://google.com/search?oq=do+a+barrel+roll&q=do+a+barrel+roll[/a]!', 'Do a Barrel Roll: <a href="https://google.com/search?oq=do+a+barrel+roll&q=do+a+barrel+roll">https://google.com/search?oq=do+a+barrel+roll&amp;q=do+a+barrel+roll</a>!');
// IMG test
test('Check out this image: [img]http://www.example.com/image.jpg[/img]', 'Check out this image: <img src="http://www.example.com/image.jpg">');
// IMG test (with args)
test('Check out this image: [img width=640 height=480]http://www.example.com/image.jpg[/img]', 'Check out this image: <img src="http://www.example.com/image.jpg" width="640" height="480">');

// Unclosed tag
test('[b]test', '<b>test</b>');
// Unterminated tag
test('[btest', '[btest');
// Tags with args that should not have args
test('[b=7]Bold [i =3]italic[/i][/b 5]', '<b>Bold <i>italic</i></b>');
// Sneaking in an HTML tag?
test('[b<a href="https://google.com?q=example[b>Ha, gotcha?[a]', '[b&lt;a href="https://google.com?q=example[b&gt;Ha, gotcha?[a]');
// Unordered tag
test('Here is [b]bold and [i]italics[/b][/i] text.', 'Here is <b>bold and <i>italics</i></b>[/i] text.');
// Mismatched aliased tag
test('A wise man once said: [quote]Do not parse HTML with a regex.[/blockquote] I think he was on to something.', 'A wise man once said: <blockquote>Do not parse HTML with a regex.</blockquote> I think he was on to something.');
// td outside tr
test('[table][tr][th]Name[/th][th]Phone[/th][/tr][td]Greg[/td][td]555-1212[/td][/table]', '<table><tr><th>Name</th><th>Phone</th></tr>[td]Greg[/td][td]555-1212[/td]</table>');

?>
