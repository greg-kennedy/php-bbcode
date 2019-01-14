<?php

// Renders a BBCode string to HTML, for inclusion into a document.
function bbcode_to_html($input) : string
{
  // Tag aliases.  Item on left translates to item on right.
  static $alias = [
    'url' => 'a',
    'code' => 'pre',
    'quote' => 'blockquote',
    '*' => 'li'
  ];

  // split input string into array using regex, UTF-8 aware
  $characters = preg_split('//u', $input, null, PREG_SPLIT_NO_EMPTY);

  // begin with the empty string
  $result = '';

  // mode defines a parse mode for the parser state machine.
  $mode = 0;
  $buffer = '';
  $tag_stack = [];

  foreach ($characters as $ch)
  {
// TODO: newline handling
    if ($mode === 0) {
      // mode 0 is outside of any tags
      if ($ch === '[') {
        // open square bracket switches to tag-parse mode
        $mode = 1;
      } elseif ($ch === '<') {
        // HTML entities
        $result .= '&lt;';
      } elseif ($ch === '>') {
        $result .= '&gt;';
      } elseif ($ch === '&') {
        $result .= '&amp;';
      } else {
        $result .= $ch;
      }
    } elseif ($mode === 1) {
      // mode 1 is after seeing an opening square brace
      if ($ch === '[') {
        // escaped bracket
        $result .= '[';
        $mode = 0;
      } elseif ($ch === '/') {
        // [/ begins a closing tag instead
        $buffer = '';
        $mode = 3;
      } elseif (preg_match('/[A-Za-z*]/', $ch)) {
        // does this look like the start of a tag...?
        $buffer = $ch;
        $mode = 2;
      } else {
        // doesn't look like a tag, unparse and move on
        $result = $result . '[' . $ch;
        $mode = 0;
      }
    } elseif ($mode === 2) {
      // mode 2 is within a square brace, but no equals or space (yet)
      if ($ch === ']') {
        // End square brace of opening tag
        $tag = strtolower($buffer);
        if (isset($alias[$tag])) {
          $tag = $alias[$tag];
        }

        // Simple tags (no validation or alternate modes)
        if ($tag === 'b' || $tag === 'i' || $tag === 'u' || $tag === 's' || $tag === 'sup' || $tag === 'sub' ||
            $tag === 'blockquote' ||
            $tag === 'ol' || $tag === 'ul' ||
            $tag === 'table') {
          array_push($tag_stack, $tag);
          $result = $result . '<' . $tag . '>';
        } elseif ($tag === 'li') {
          // Disallow [li] outside of [ol] or [ul]
          if (array_search('ol', $tag_stack, TRUE) !== FALSE ||
              array_search('ul', $tag_stack, TRUE) !== FALSE) {
            array_push($tag_stack, 'li');
            $result .= '<li>';
          } else {
            $result = $result . '[' . $buffer . ']';
          }
        } elseif ($tag === 'tr') {
          // Disallow [tr] outside of [table]
          if (array_search('table', $tag_stack, TRUE) !== FALSE) {
            array_push($tag_stack, 'tr');
            $result .= '<tr>';
          } else {
            $result = $result . '[' . $buffer . ']';
          }
        } elseif ($tag === 'td' || $tag === 'th') {
          // Disallow [th] / [td] outside of [tr] outside of [table]
          $tr_index = array_search('tr', $tag_stack, TRUE);
          $table_index = array_search('table', $tag_stack, TRUE);
          if ($tr_index !== FALSE && $table_index !== FALSE && $table_index < $tr_index) {
            array_push($tag_stack, $tag);
            $result = $result . '<' . $tag . '>';
          } else {
            $result = $result . '[' . $buffer . ']';
          }
        } elseif ($tag === 'a') {
//TODO: url parsing mode
        } elseif ($tag === 'img') {
//TODO: img parsing mode
        } elseif ($tag === 'pre') {
          array_push($tag_stack, 'pre');
          $result .= '<pre>';
//TODO: code parsing mode
        } else {
          // Unrecognized tag!
          $result = $result . '[' . $buffer . ']';
        }
        $mode = 0;
      } elseif ($ch === '=') {
        // arguments following a tag name
        //  this is only allowed for URL tags or font shorthands
        $tag = strtolower($buffer);
        if (isset($alias[$tag])) {
          $tag = $alias[$tag];
        }

        if ($tag === 'a') {
//TODO: url parsing mode
        } elseif ($tag === 'color') {
//TODO: color parsing mode
        } elseif ($tag === 'size') {
//TODO: size parsing mode
        } else {
          // Unrecognized tag!
          $result = $result . '[' . $buffer . '=';
          $mode = 0;
        }
      } elseif ($ch === ' ') {
        // arguments following a tag name, go into args capture mode
        //  this is for FONT tag
        $tag = strtolower($buffer);
        if (isset($alias[$tag])) {
          $tag = $alias[$tag];
        }

        if ($tag === 'font') {
//TODO: size parsing mode
        } else {
          // Unrecognized tag!
          $result = $result . '[' . $buffer . ' ';
          $mode = 0;
        }
      } elseif (preg_match('/[A-Za-z]/u', $ch)) {
        // Tag continues, maybe
        $buffer .= $ch;
      } else {
        // Illegal character in tag, just print everything we have so far and return
        $result = $result . '[' . $buffer . $ch;
        $mode = 0;
      }
    } elseif ($mode === 3) {
      // mode 3 is within a closing tag
      if ($ch === ']') {
        // Tag end
        $tag = strtolower($buffer);
        // tag aliases
        if (isset($alias[$tag])) {
          $tag = $alias[$tag];
        }

        if (array_search($buffer, $tag_stack, TRUE) === FALSE) {
          // Attempted to close a tag that was not on the stack!
          $result = $result . '[/' . $buffer . ']';
        } else {
          //pop repeatedly until we pop the tag, and close everything on the way
          do {
            $popped_tag = array_pop($tag_stack);
            $result = $result . '</' . $popped_tag . '>';
          } while ($tag !== $popped_tag);
        }
        $mode = 0;
      } elseif (preg_match('/[A-Za-z*]/u', $ch)) {
        // Closing-tag continues
        $buffer .= $ch;
      } else {
        // Illegal character in tag, just print it and return
        $result = $result . '[/' . $buffer . $ch;
        $mode = 0;
      }
    } else {
      //$mode = 0;
      throw new Exception("bbcode_to_html: reached invalid mode $mode");
    }
  }

  // Close any remaining stray tags left on the stack
  while ($tag_stack)
  {
    $tag = array_pop($tag_stack);
    $result = $result . '</' . $tag . '>';
  }

  // All done!
  return $result;
}
