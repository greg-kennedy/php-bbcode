<?php

/******************************************************************************

php-bbcode
  BBCode to HTML conversion, in PHP7.

Greg Kennedy <kennedy.greg@gmail.com>, 2018
  https://github.com/greg-kennedy/php-bbcode

This is public domain software.  Please see LICENSE for more details.

******************************************************************************/

// Const definitions for mode

// Normal parsing mode
const _BBCODE_STATE_DEFAULT = 0;

const _BBCODE_STATE_OPENING_BRACKET = 1;
const _BBCODE_STATE_OPENING_TAG = 2;
const _BBCODE_STATE_OPENING_TAG_ARGS = 3;

const _BBCODE_STATE_CLOSING_TAG = 4;

// Plaintext parsing (disables newline handling, disables further tag nesting)
const _BBCODE_STATE_RAW = 5;
const _BBCODE_STATE_RAW_BRACKET = 6;
const _BBCODE_STATE_RAW_TAG = 7;

// URL parsing modes
const _BBCODE_STATE_URL = 8;
const _BBCODE_STATE_URL_BRACKET = 9;
const _BBCODE_STATE_URL_TAG = 10;


// Tag aliases.  Item on left translates to item on right.
const _BBCODE_TAG_ALIAS = [
  'url' => 'a',
  'code' => 'pre',
  'quote' => 'blockquote',
  '*' => 'li'
];

// helper function: normalize a potential "tag"
function _bbcode_tag($input) : string
{
  $tag = strtolower($input);
  if (isset(_BBCODE_TAG_ALIAS[$tag])) {
    return _BBCODE_TAG_ALIAS[$tag];
  }
  return $tag;
}

// helper function: normalize HTML entities
function _bbcode_entity($ch) : string
{
  if ($ch === '<') {
    return '&lt;';
  }
  if ($ch === '>') {
    return '&gt;';
  }
  if ($ch === '&') {
    return '&amp;';
  }
  if ($ch === "\u{00A0}") {
    return '&nbsp;';
  }
  return $ch;
}

// Renders a BBCode string to HTML, for inclusion into a document.
function bbcode_to_html($input) : string
{
  // split input string into array using regex, UTF-8 aware
  $characters = preg_split('//u', $input, null, PREG_SPLIT_NO_EMPTY);

  // begin with the empty string
  $result = '';

  // mode defines a parse mode for the parser state machine.
  $state = _BBCODE_STATE_DEFAULT;
  // "buffer" is mode-specific storage ("register"?)
  $buffer = '';
  // url variable
  $url = '';
  $tag_stack = [];

  foreach ($characters as $ch)
  {
// TODO: newline handling

//echo "now seeing '$ch' (state: $state)\n";

    /////////////////////////////////////////////////////////////
    // NORMAL STATE
    if ($state === _BBCODE_STATE_DEFAULT) {
      if ($ch === '[') {
        // open square bracket switches to tag-parse mode
        $state = _BBCODE_STATE_OPENING_BRACKET;
      } else {
        $result .= _bbcode_entity($ch);
      }
    } elseif ($state === _BBCODE_STATE_OPENING_BRACKET) {
      // mode 1 is after seeing an opening square brace
      if ($ch === '[') {
        // escaped bracket
        $result .= '[';
        $state = _BBCODE_STATE_DEFAULT;
      } elseif ($ch === '/') {
        // [/ begins a closing tag instead
        $buffer = '';
        $state = _BBCODE_STATE_CLOSING_TAG;
      } elseif (preg_match('/[A-Za-z*]/u', $ch)) {
        // does this look like the start of a tag...?
        $buffer = $ch;
        $state = _BBCODE_STATE_OPENING_TAG;
      } else {
        // doesn't look like a tag, unparse and move on
        $result = $result . '[' . _bbcode_entity($ch);
        $state = _BBCODE_STATE_DEFAULT;
      }
    } elseif ($state === _BBCODE_STATE_OPENING_TAG) {
      // mode 2 is within a square brace, but no equals or space (yet)
      if ($ch === ']') {
        // End square brace of opening tag
        $tag = _bbcode_tag($buffer);

        // Simple tags (no validation or alternate modes)
        if ($tag === 'b' || $tag === 'i' || $tag === 'u' || $tag === 's' || $tag === 'sup' || $tag === 'sub' ||
            $tag === 'blockquote' ||
            $tag === 'ol' || $tag === 'ul' ||
            $tag === 'table') {
          array_push($tag_stack, $tag);
          $result = $result . '<' . $tag . '>';
          $state = _BBCODE_STATE_DEFAULT;
        } elseif ($tag === 'li') {
          // Disallow [li] outside of [ol] or [ul]
          if (array_search('ol', $tag_stack, TRUE) !== FALSE ||
              array_search('ul', $tag_stack, TRUE) !== FALSE) {
            array_push($tag_stack, 'li');
            $result .= '<li>';
          } else {
            $result = $result . '[' . $buffer . ']';
          }
          $state = _BBCODE_STATE_DEFAULT;
        } elseif ($tag === 'tr') {
          // Disallow [tr] outside of [table]
          if (array_search('table', $tag_stack, TRUE) !== FALSE) {
            array_push($tag_stack, 'tr');
            $result .= '<tr>';
          } else {
            $result = $result . '[' . $buffer . ']';
          }
          $state = _BBCODE_STATE_DEFAULT;
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
          $state = _BBCODE_STATE_DEFAULT;
        } elseif ($tag === 'pre') {
          // [pre] / [code] put us into RAW mode, where nothing is parsed except a close tag
          array_push($tag_stack, 'pre');
          $result .= '<pre>';
          $state = _BBCODE_STATE_RAW;
        } elseif ($tag === 'a' || $tag === 'img') {
          // These options place the reader into "exclusive" mode, which prevents
          //  further nesting of tags until this one is closed.
          array_push($tag_stack, $tag);
          $url = '';
          $state = _BBCODE_STATE_URL;
        } else {
          // Unrecognized tag!
          $result = $result . '[' . $buffer . ']';
          $state = _BBCODE_STATE_DEFAULT;
        }
      } elseif ($ch === '=') {
        // arguments following a tag name
        //  this is only allowed for URL tags or font shorthands
        $tag = _bbcode_tag($buffer);

        if ($tag === 'a') {
//TODO: url parsing mode IN A TAG
        } elseif ($tag === 'color') {
//TODO: color parsing mode
        } elseif ($tag === 'size') {
//TODO: size parsing mode
        } else {
          // Unrecognized tag!
          $result = $result . '[' . $buffer . '=';
          $state = _BBCODE_STATE_DEFAULT;
        }
      } elseif ($ch === ' ') {
        // arguments following a tag name, go into args capture mode
        //  this is for FONT tag
        $tag = _bbcode_tag($buffer);

        if ($tag === 'font') {
//TODO: font parsing mode
        } elseif ($tag === 'img') {
//TODO: image display options
        } else {
          // Unrecognized tag!
          $result = $result . '[' . $buffer . ' ';
          $state = _BBCODE_STATE_DEFAULT;
        }
      } elseif (preg_match('/[A-Za-z]/u', $ch)) {
        // Tag continues, maybe
        $buffer .= $ch;
      } else {
        // Illegal character in tag, just print everything we have so far and return
        $result = $result . '[' . $buffer . _bbcode_entity($ch);
        $state = _BBCODE_STATE_DEFAULT;
      }
    } elseif ($state === _BBCODE_STATE_CLOSING_TAG) {
      // mode 3 is within a closing tag
      if ($ch === ']') {
        // Tag end
        $tag = _bbcode_tag($buffer);

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
        $state = _BBCODE_STATE_DEFAULT;
      } elseif (preg_match('/[A-Za-z*]/u', $ch)) {
        // Closing-tag continues
        $buffer .= $ch;
      } else {
        // Illegal character in tag, just print it and return
        $result = $result . '[/' . $buffer . _bbcode_entity($ch);
        $state = _BBCODE_STATE_DEFAULT;
      }

    /////////////////////////////////////////////////////////////
    // RAW STATE
    } elseif ($state === _BBCODE_STATE_RAW) {
      // RAW parsing mode disables nesting and newline conversion
      if ($ch === '[') {
        // open square bracket switches to tag-parse mode
        $state = _BBCODE_STATE_RAW_BRACKET;
      } else {
        $result .= _bbcode_entity($ch);
      }
    } elseif ($state === _BBCODE_STATE_RAW_BRACKET) {
      // mode 1 is after seeing an opening square brace
      if ($ch === '/') {
        // [/ begins a closing tag instead
        $buffer = '';
        $state = _BBCODE_STATE_RAW_TAG;
      } else {
        // doesn't look like a tag, unparse and move on
        $result = $result . '[' . _bbcode_entity($ch);
        $state = _BBCODE_STATE_RAW;
      }
    } elseif ($state === _BBCODE_STATE_RAW_TAG) {
      // mode 3 is within a closing tag
      if ($ch === ']') {
        // Tag end
        $tag = _bbcode_tag($buffer);

        // Pop the last tag for compare
        $popped_tag = array_pop($tag_stack);
        if ($tag === $popped_tag) {
          // finally, complete
          $result = $result . '</' . $popped_tag . '>';
          $state = _BBCODE_STATE_DEFAULT;
        } else {
          // cripes, that's not it, put it back
          array_push($tag_stack, $popped_tag);
          $result = $result . '[/' . $buffer . ']';
        }
        $buffer = '';
      } elseif (preg_match('/[A-Za-z*]/u', $ch)) {
        // Closing-tag continues
        $buffer .= $ch;
      } else {
        // Illegal character in tag, just print it and return
        $result = $result . '[/' . $buffer . _bbcode_entity($ch);
        $state = _BBCODE_STATE_RAW;
      }

    // URL STATE
    } elseif ($state === _BBCODE_STATE_URL) {
      // URL mode has only a certain whitelist of characters allowed
      // ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~:/?#[]@!$&'()*+,;=
      if ($ch === '[') {
        // TODO: Square bracket is actually a valid character in URLs,
        //  but BBCode uses it as a delimiter... use %5D instead
        $state = _BBCODE_STATE_URL_BRACKET;
      } elseif (preg_match("/[A-Za-z0-9\-._~:\/?#\]@!$&'()*+,;=%]/u", $ch)) {
        // Valid URL character, I guess
        $url .= $ch;
      } else {
        // On second thought, this doesn't look like a URL.  Better not paste it.
        //  TODO: this is unsafe as $url is not escaped!!
        $result = $result . '[' . $buffer . ']' . $url . _bbcode_entity($ch);
        $state = _BBCODE_STATE_DEFAULT;
      }
    } elseif ($state === _BBCODE_STATE_URL_BRACKET) {
      // mode 1 is after seeing an opening square brace
      if ($ch === '/') {
        // [/ begins a closing tag instead
        $buffer = '';
        $state = _BBCODE_STATE_URL_TAG;
      } else {
        // doesn't look like a tag, unparse and move on
        $result = $result . '[' . _bbcode_entity($ch);
        $state = _BBCODE_STATE_URL;
      }
    } elseif ($state === _BBCODE_STATE_URL_TAG) {
      // mode 3 is within a closing tag
      if ($ch === ']') {
        // Tag end
        $tag = _bbcode_tag($buffer);

        // Pop the last tag for compare
        $popped_tag = array_pop($tag_stack);
        if ($tag === $popped_tag) {
          // finally, complete
          if ($tag === 'a') {
            $result = $result . '<a href="' . $url . '">' . $url . '</a>';
            $state = _BBCODE_STATE_DEFAULT;
          } elseif ($tag === 'img') {
            $result = $result . '<img src="' . $url . '">';
            $state = _BBCODE_STATE_DEFAULT;
          } else {
            throw new Exception("bbcode_to_html: Parsed a URL, but don't know how to output one for $tag...");
          }
        } else {
          // cripes, that's not it, put it back
          array_push($tag_stack, $popped_tag);
          $result = $result . '[/' . $buffer . ']';
        }
        $buffer = '';
      } elseif (preg_match('/[A-Za-z*]/u', $ch)) {
        // Closing-tag continues
        $buffer .= $ch;
      } else {
        // Illegal character in tag, just print it and return
        $result = $result . '[/' . $buffer . _bbcode_entity($ch);
        $state = _BBCODE_STATE_RAW;
      }

    /////////////////////////////////////////////////////////////
    // ERROR STATE
    } else {
      //$state = 0;
      throw new Exception("bbcode_to_html: reached invalid mode $state");
    }
  }

// TODO: Handling for all modes.
  // Close any remaining stray tags left on the stack
  while ($tag_stack)
  {
    $tag = array_pop($tag_stack);
    $result = $result . '</' . $tag . '>';
  }

  // All done!
  return $result;
}
