<?php

/******************************************************************************

php-bbcode
  BBCode to HTML conversion, in PHP7.

Greg Kennedy <kennedy.greg@gmail.com>, 2018
  https://github.com/greg-kennedy/php-bbcode

This is public domain software.  Please see LICENSE for more details.

******************************************************************************/

class BBCode
{
  // Tag aliases.  Item on left translates to item on right.
  const TAG_ALIAS = [
    'url' => 'a',
    'code' => 'pre',
    'quote' => 'blockquote',
    '*' => 'li'
  ];

  // helper function: normalize a potential "tag"
  static private function normalize($input) : string
  {
    $tag = strtolower($input);
    if (isset(self::TAG_ALIAS[$tag])) {
      return self::TAG_ALIAS[$tag];
    }
    return $tag;
  }

  // helper function: normalize HTML entities, with newline handling
  static private function encode($input) : string
  {
    // break substring into individual unicode chars
    $characters = preg_split('//u', $input, null, PREG_SPLIT_NO_EMPTY);

    // append each one-at-a-time to create output
    $lf = 0;
    $output = '';
    foreach ($characters as &$ch)
    {
      if ($ch === "\n") {
        $lf ++;
      } elseif ($ch === "\r") {
        continue;
      } else {
        if ($lf === 1) {
          $output .= "\n<br>";
          $lf = 0;
        } elseif ($lf > 1) {
          $output .= "\n\n<p>";
          $lf = 0;
        }

        if ($ch === '<') {
          $output .= '&lt;';
        } elseif ($ch === '>') {
          $output .= '&gt;';
        } elseif ($ch === '&') {
          $output .= '&amp;';
        } elseif ($ch === "\u{00A0}") {
          $output .= '&nbsp;';
        } else {
          $output .= $ch;
        }
      }
    }

    // trailing linefeed handle
    if ($lf === 1) {
      $output .= "\n<br>";
    } elseif ($lf > 1) {
      $output .= "\n\n<p>";
    }

    return $output;
  }

  // Renders a BBCode string to HTML, for inclusion into a document.
  static public function bbcode_to_html($input) : string
  {
    // split input string into array using regex, UTF-8 aware
    //  this should give us tokens to work with

    // The regex is just any printable ASCII char, excluding square brackets,
    //  and enclosed within square brackets.
    $match_count = preg_match_all('/\[[\x20-\x5a\x5c\x5e-\x7e]+\]/u',
      $input, $matches, PREG_OFFSET_CAPTURE);
    if ($match_count === FALSE) {
      throw new RuntimeException('Fatal error in preg_match_all for BBCode tags');
    }

    // begin with the empty string
    $output = '';
    $input_ptr = 0;

    $stack = [];
    for ($match_idx = 0; $match_idx < $match_count; $match_idx ++)
    {
      list($tag, $offset) = $matches[0][$match_idx];

      // pick up chars between tags and HTML-encode them
      $output .= self::encode(substr($input, $input_ptr, $offset - $input_ptr));

      // advance input_ptr to just past the current tag
      $input_ptr = $offset + strlen($tag);

      if ($tag[1] === '/') {
        // CLOSING TAG
        //   get "standardized" tag name
        $type = self::normalize(substr($tag, 2, -1));

        if (array_search($type, $stack, TRUE) === FALSE) {
          // Attempted to close a tag that was not on the stack!
          $output = $output . $tag;
        } else {
          //pop repeatedly until we pop the tag, and close everything on the way
          do {
            $popped_type = array_pop($stack);
            $output = $output . '</' . $popped_type . '>';
          } while ($type !== $popped_type);
        }
      } else {
        // Opening tag?

        // parse tag into a set of args: first split by spaces,
        //  then split those by =
        $params = explode(' ', substr($tag, 1, -1));
        $args = [];
        foreach ($params as &$param) {
          $args[] = explode('=', $param, 2);
        }

        // determine tag type from first captured arg
        $type = self::normalize($args[0][0]);

        // Simple tags (no validation or alternate modes)
        if ($type === 'b' || $type === 'i' || $type === 'u' || $type === 's' || $type === 'sup' || $type === 'sub' ||
            $type === 'blockquote' ||
            $type === 'ol' || $type === 'ul' ||
            $type === 'table') {
          array_push($stack, $type);
          $output = $output . '<' . $type . '>';
        } elseif ($type === 'li') {
          // Disallow [li] outside of [ol] or [ul]
          if (array_search('ol', $stack, TRUE) !== FALSE ||
              array_search('ul', $stack, TRUE) !== FALSE) {
            array_push($stack, 'li');
            $output .= '<li>';
          } else {
            $output = $output . $tag;
          }
        } elseif ($type === 'tr') {
          // Disallow [tr] outside of [table]
          if (array_search('table', $stack, TRUE) !== FALSE) {
            array_push($stack, 'tr');
            $output .= '<tr>';
          } else {
            $output = $output . $tag;
          }
        } elseif ($type === 'td' || $type === 'th') {
          // Disallow [th] / [td] outside of [tr] outside of [table]
          $tr_index = array_search('tr', $stack, TRUE);
          $table_index = array_search('table', $stack, TRUE);
          if ($tr_index !== FALSE && $table_index !== FALSE && $table_index < $tr_index) {
            array_push($stack, $type);
            $output = $output . '<' . $type . '>';
          } else {
            $output = $output . $tag;
          }

        // CUSTOM TAG HANDLING
        } elseif ($type === 'pre') {
          // [pre] / [code] put us into RAW mode, where nothing is parsed except [/code]

          for ($i = $match_idx + 1; $i < $match_count; $i ++)
          {
            list($search_tag, $search_offset)  = $matches[0][$i];
            if ($search_tag[1] !== '/') { continue; }
            if (self::normalize(substr($search_tag, 2, -1)) === 'pre') { break; }
          }

          if ($i < $match_count) {
            // successfully found ending tag

            // encode everything contained between here and there
            $output = $output . '<pre>' . self::encode(substr($input, $input_ptr, $search_offset - $input_ptr)) . '</pre>';
            // advance ptr (again)
            $input_ptr = $search_offset + strlen($search_tag);
            // update search position
            $match_idx = $i;
          } else {
            // Unrecognized type!
            $output = $output . $tag;
          }
        } elseif ($type === 'a') {
          // URL handling.  Two modes: [a=url]title[/a] and [a]url[/a].
          //  Verify enclosing value first.
          $buffer = null;
          $i = $match_idx + 1;
          if ($i < $match_count) {
            list($search_tag, $search_offset)  = $matches[0][$i];
            if ($search_tag[1] === '/') {
              if (self::normalize(substr($search_tag, 2, -1)) === 'a') {
                $buffer = substr($input, $input_ptr, $search_offset - $input_ptr);
              }
            }
          }

          // matched something in the middle
          if (isset($buffer)) {
            if (isset($args[0][1])) {
              // $buffer is the title
              $url = $args[0][1];
            } else {
              // $buffer is the url
              $url = $buffer;
            }
            // emit the tag
            $output = $output . '<a href="' . $url . '">' . self::encode($buffer) . '</a>';
            // advance ptr (again)
            $input_ptr = $search_offset + strlen($search_tag);
            // update search position
            $match_idx = $i;
          } else {
            // Unrecognized type!
            $output = $output . $tag;
          }

        } elseif ($type === 'img') {
          // image handling.  [img (optional=args go=here)]url[/img].
          //  Verify enclosing value first.
          $buffer = null;
          $i = $match_idx + 1;
          if ($i < $match_count) {
            list($search_tag, $search_offset)  = $matches[0][$i];
            if ($search_tag[1] === '/') {
              if (self::normalize(substr($search_tag, 2, -1)) === 'img') {
                $buffer = substr($input, $input_ptr, $search_offset - $input_ptr);
              }
            }
          }

          // matched something in the middle
          if (isset($buffer)) {
            // emit the tag
            $output = $output . '<img src="' . $buffer . '">';
            // advance ptr (again)
            $input_ptr = $search_offset + strlen($search_tag);
            // update search position
            $match_idx = $i;
          } else {
            // Unrecognized type!
            $output = $output . $tag;
          }


        // ADD CUSTOM TAGS HERE

        } else {
          // Unrecognized type!
          $output = $output . $tag;
        }
      }
    }

    // pick up any stray chars and HTML-encode them
    $output .= self::encode(substr($input, $input_ptr));

    // Close any remaining stray tags left on the stack
    while ($stack)
    {
      $tag = array_pop($stack);
      $output = $output . '</' . $tag . '>';
    }

    return $output;
  }
}

// procedural
function bbcode_to_html($input) : string
{
  return BBCode::bbcode_to_html($input);
}
