<?php

require_once('bbcode.php');

function test($a, $b)
{
  static $test_num = 0;

  $test_num ++;
  echo "Test #", $test_num, ": ";

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
}

// No formatting
test('test', 'test');
// HTML escaping
test('<b>test</b>', '&lt;b&gt;test&lt;/b&gt;');

// Bold text
test('[b]test[/b]', '<b>test</b>');
// Fake tag
test('[spoiler]test[/spoiler]', '[spoiler]test[/spoiler]');
// Escaped bracket
test('[u]To make underlined text, type [[u].[/u]', '<u>To make underlined text, type [u].</u>');

// Unclosed tag
test('[b]test', '<b>test</b>');
// Unordered tag
test('Here is [b]bold and [i]italics[/b][/i] text.', 'Here is <b>bold and <i>italics</i></b>[/i] text.');
// td outside tr
test('[table][tr][th]Name[/th][th]Phone[/th][/tr][td]Greg[/td][td]555-1212[/td][/table]','<table><tr><th>Name</th><th>Phone</th></tr>[td]Greg[/td][td]555-1212[/td]</table>');

?>
