<?php

/* helper function: close a tag */
function _bbcode_close_tag($tag) : string
{
  if ($tag === 'b' || $tag === 'i' || $tag === 'u' || $tag === 's' || $tag === 'sup' || $tag === 'sub' ||
      $tag === 'ul' || $tag === 'ol' ||
      $tag === 'table' || $tag === 'tr' || $tag === 'th' || $tag === 'td')
  {
    return '</' . $tag . '>';
//TODO: url, url=
  }
  if ($tag === 'img') {
//TODO: img parsing mode
    return '">';
  }
  if ($tag === 'pre' || $tag === 'code') {
    return '</pre>';
//TODO: code parsing mode
  }
  if ($tag === 'quote') {
    return '</blockquote>';
//TODO: quote with author
//TODO: font styles
  /*}
  if ($tag === 'size') {
    $result .= '<span style="font-size:30px">';*/
  /*}
  if ($tag === 'color') {
    $result .= '<span style="font-color:30px">';*/
//TODO: [list]
  }
  if ($tag === 'li' || $tag === '*') {
    return '</li>';
  }

  // This should never happen: we somehow pushed a tag above that we are not able to handle here
  //$result = $result . '</' . $tag . '>';
  throw new Exception("Error: tried to close tag $tag");
}

/* Renders a post to HTML, for inclusion into a document. */
function bbcode_to_html($input) : string
{
  /* split input string into array using regex, UTF-8 aware */
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
      // mode 2 is within a square brace
      if ($ch === ']') {
        // End square brace of opening tag
        //  Try to split tag, args
        $arr = explode('=', $buffer);
        $tag = strtolower($arr[0]);

        // Push the tag into the tag_stack for pop later
        if ($tag === 'b' || $tag === 'i' || $tag === 'u' || $tag === 's' || $tag === 'sup' || $tag === 'sub') {
          array_push($tag_stack, $tag);
          $result = $result . '<' . $tag . '>';
//TODO: url, url=
        } elseif ($tag === 'img') {
//TODO: img parsing mode
          array_push($tag_stack, $tag);
          $result .= '<img src="';
        } elseif ($tag === 'pre' || $tag === 'code') {
          array_push($tag_stack, 'pre');
          $result .= '<pre>';
//TODO: code parsing mode
        } elseif ($tag === 'quote') {
          array_push($tag_stack, 'quote');
          $result .= '<blockquote>';
//TODO: quote with author
//TODO: font styles
        /*} elseif ($tag === 'size') {
          $result .= '<span style="font-size:30px">';*/
        /*} elseif ($tag === 'color') {
          $result .= '<span style="font-color:30px">';*/
//TODO: [list]
        } elseif ($tag === 'ul') {
          array_push($tag_stack, 'ul');
          $result .= '<ul>';
        } elseif ($tag === 'ol') {
          array_push($tag_stack, 'ol');
          $result .= '<ol>';
        } elseif ($tag === 'li' || $tag === '*') {
          // Disallow [li] outside of [ol] or [ul]
          if (array_search('ol', $tag_stack, TRUE) !== FALSE ||
              array_search('ul', $tag_stack, TRUE) !== FALSE) {
            array_push($tag_stack, 'li');
            $result .= '<li>';
          } else {
            $result = $result . '[' . $buffer . ']';
          }
        } elseif ($tag === 'table') {
          array_push($tag_stack, 'table');
          $result .= '<table>';
        } elseif ($tag === 'tr') {
          // Disallow [tr] outside of [table]
          if (array_search('table', $tag_stack, TRUE) !== FALSE) {
            array_push($tag_stack, 'tr');
            $result .= '<tr>';
          } else {
            $result = $result . '[' . $buffer . ']';
          }
        } elseif ($tag === 'td' || $tag === 'th') {
          // Disallow [th] / [td] outside of [tr]
          if (array_search('tr', $tag_stack, TRUE) !== FALSE) {
            array_push($tag_stack, $tag);
            $result = $result . '<' . $tag . '>';
          } else {
            $result = $result . '[' . $buffer . ']';
          }
        } else {
          // Unrecognized tag!
          $result = $result . '[' . $buffer . ']';
        }
        $mode = 0;
      } elseif (preg_match('/[A-Za-z0-9= *-]/u', $ch)) {
        // Tag continues
        $buffer .= $ch;
      } else {
        // Illegal character in tag, just print everything we have so far and return
        $result = $result . '[' . $buffer . $ch;
        $mode = 0;
      }
    } elseif ($mode === 3) {
      // mode 3 is within a closing tag
      if ($ch === ']') {
        // Tag end, check the tag stack
        $buffer = strtolower($buffer);
        if (array_search($buffer, $tag_stack, TRUE) === FALSE) {
          // Attempted to close a tag that was not on the stack!
          $result = $result . '[/' . $buffer . ']';
        } else {
//TODO: pop repeatedly until we pop the tag, and close everything on the way
          do {
            $tag = array_pop($tag_stack);
            $result .= _bbcode_close_tag($tag);
          } while ($tag !== $buffer);
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
    } elseif ($mode === 4) {
//TODO: CODE tag and IMAGE tag and URL tag and...
    } else {
      $mode = 0;
    }
  }

  // Close any remaining stray tags left on the stack
  while ($tag_stack)
  {
    $result .= _bbcode_close_tag(array_pop($tag_stack));
  }

  // All done!
  return $result;
}
