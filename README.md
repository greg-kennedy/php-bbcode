# php-bbcode
BBCode to HTML conversion, in PHP7.

## What is it?
This is a PHP include that can convert BBCode into safe HTML output.

Features:
* UTF-8 aware
* Replaces unsafe HTML characters (`<`, `>`, `&`) with safe HTML entities (`&lt;`, `&gt;`, `&amp;`)
* Not regex-based: uses a char-at-a-time state machine parser to properly close tags and avoid malformed HTML
* Public-domain (The Unlicense), just copy-paste into your own projects!

## What does it support?
These BBCode tags are supported and converted to HTML equivalent:

* `[[` (escapes to `[`)
* `[b]`, `[i]`, `[u]`, `[s]`, `[sub]`, `[sup]`
* `[color=X]`, `[size=Y]`, `[font color=X size=Y]`
* `[quote]` / `[blockquote]`
* `[pre]` / `[code]`
* `[ol]`, `[ul]`, `[li]` / `[*]`
* `[table]`, `[tr]`, `[th]`, `[td]`
* `[img]http://www.example.com/image.jpg[/img]`
* `[url]http://www.example.com[/url]` / `[url=http://www.example.com]Example URL[/url]`

In addition, unsafe HTML characters are converted to safe HTML entities.  Newlines are collapsed and translated as follows:

* `\r` characters are stripped
* Single `\n`: converted to `\n<br>`
* Two or more sequential `\n`: converted to `\n\n<p>`

The resulting string is whitespace trimmed at beginning and end.

## Notes
The common use case `[quote author=X]` is deliberately excluded, because formatting the result for presentation is implementation-dependent.

## Distribution
bbcode.php is the include file.  Place it in the folder of your choice and then `include_once 'bbcode.php'`;

test.php is a unit test file.  You do not need this when deploying.

## Contact
Open an issue if you find a bug.  Send a pull request if you want to extend it.  I especially like more unit tests, better attempts at catching malformed tags, performance and optimization features.
