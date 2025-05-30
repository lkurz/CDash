<?php

/**
 *  Base include file for SimpleTest.
 *
 * @version    $Id$
 */

/**#@+
 *  include other SimpleTest class files
 */
require_once dirname(__FILE__) . '/test_case.php';
require_once dirname(__FILE__) . '/browser.php';
require_once dirname(__FILE__) . '/page.php';
require_once dirname(__FILE__) . '/expectation.php';
/**#@-*/

/**
 *    Test for an HTML widget value match.
 */
class FieldExpectation extends SimpleExpectation
{
    private $value;

    /**
     *    Sets the field value to compare against.
     *
     * @param mixed $value Test value to match. Can be an
     *                     expectation for say pattern matching.
     * @param string $message Optiona message override. Can use %s as
     *                        a placeholder for the original message.
     */
    public function __construct($value, $message = '%s')
    {
        parent::__construct($message);
        if (is_array($value)) {
            sort($value);
        }
        $this->value = $value;
    }

    /**
     *    Tests the expectation. True if it matches
     *    a string value or an array value in any order.
     *
     * @param mixed $compare Comparison value. False for
     *                       an unset field.
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        if ($this->value === false) {
            return $compare === false;
        }
        if ($this->isSingle($this->value)) {
            return $this->testSingle($compare);
        }
        if (is_array($this->value)) {
            return $this->testMultiple($compare);
        }
        return false;
    }

    /**
     *    Tests for valid field comparisons with a single option.
     *
     * @param mixed $value value to type check
     *
     * @return bool true if integer, string or float
     */
    protected function isSingle($value)
    {
        return is_string($value) || is_integer($value) || is_float($value);
    }

    /**
     *    String comparison for simple field with a single option.
     *
     * @param mixed $compare string to test against
     *
     * @returns boolean         True if matching.
     */
    protected function testSingle($compare)
    {
        if (is_array($compare) && count($compare) == 1) {
            $compare = $compare[0];
        }
        if (!$this->isSingle($compare)) {
            return false;
        }
        return $this->value == $compare;
    }

    /**
     *    List comparison for multivalue field.
     *
     * @param mixed $compare list in any order to test against
     *
     * @returns boolean         True if matching.
     */
    protected function testMultiple($compare)
    {
        if (is_string($compare)) {
            $compare = [$compare];
        }
        if (!is_array($compare)) {
            return false;
        }
        sort($compare);
        return $this->value === $compare;
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        if (is_array($compare)) {
            sort($compare);
        }
        if ($this->test($compare)) {
            return 'Field expectation [' . $dumper->describeValue($this->value) . ']';
        } else {
            return 'Field expectation [' . $dumper->describeValue($this->value) .
            '] fails with [' .
            $dumper->describeValue($compare) . '] ' .
            $dumper->describeDifference($this->value, $compare);
        }
    }
}

/**
 *    Test for a specific HTTP header within a header block.
 */
class HttpHeaderExpectation extends SimpleExpectation
{
    private $expected_header;
    private $expected_value;

    /**
     *    Sets the field and value to compare against.
     *
     * @param string $header case insenstive trimmed header name
     * @param mixed $value Optional value to compare. If not
     *                     given then any value will match. If
     *                     an expectation object then that will
     *                     be used instead.
     * @param string $message Optiona message override. Can use %s as
     *                        a placeholder for the original message.
     */
    public function __construct($header, $value = false, $message = '%s')
    {
        parent::__construct($message);
        $this->expected_header = $this->normaliseHeader($header);
        $this->expected_value = $value;
    }

    /**
     *    Accessor for aggregated object.
     *
     * @return mixed expectation set in constructor
     */
    protected function getExpectation()
    {
        return $this->expected_value;
    }

    /**
     *    Removes whitespace at ends and case variations.
     *
     * @param string $header name of header
     * @param string            trimmed and lowecased header
     *                             name
     */
    protected function normaliseHeader($header)
    {
        return strtolower(trim($header));
    }

    /**
     *    Tests the expectation. True if it matches
     *    a string value or an array value in any order.
     *
     * @param mixed $compare raw header block to search
     *
     * @return bool true if header present
     */
    public function test($compare)
    {
        return is_string($this->findHeader($compare));
    }

    /**
     *    Searches the incoming result. Will extract the matching
     *    line as text.
     *
     * @param mixed $compare raw header block to search
     *
     * @return string matching header line
     */
    protected function findHeader($compare)
    {
        $lines = explode("\r\n", $compare);
        foreach ($lines as $line) {
            if ($this->testHeaderLine($line)) {
                return $line;
            }
        }
        return false;
    }

    /**
     *    Compares a single header line against the expectation.
     *
     * @param string $line a single line to compare
     *
     * @return bool true if matched
     */
    protected function testHeaderLine($line)
    {
        if (count($parsed = explode(':', $line, 2)) < 2) {
            return false;
        }
        [$header, $value] = $parsed;
        if ($this->normaliseHeader($header) != $this->expected_header) {
            return false;
        }
        return $this->testHeaderValue($value, $this->expected_value);
    }

    /**
     *    Tests the value part of the header.
     *
     * @param string $value value to test
     * @param mixed $expected value to test against
     *
     * @return bool true if matched
     */
    protected function testHeaderValue($value, $expected)
    {
        if ($expected === false) {
            return true;
        }
        if (SimpleExpectation::isExpectation($expected)) {
            return $expected->test(trim($value));
        }
        return trim($value) == trim($expected);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare raw header block to search
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        if (SimpleExpectation::isExpectation($this->expected_value)) {
            $message = $this->expected_value->overlayMessage($compare, $this->getDumper());
        } else {
            $message = $this->expected_header .
                ($this->expected_value ? ': ' . $this->expected_value : '');
        }
        if (is_string($line = $this->findHeader($compare))) {
            return "Searching for header [$message] found [$line]";
        } else {
            return "Failed to find header [$message]";
        }
    }
}

/**
 *    Test for a specific HTTP header within a header block that
 *    should not be found.
 */
class NoHttpHeaderExpectation extends HttpHeaderExpectation
{
    private $expected_header;
    private $expected_value;

    /**
     *    Sets the field and value to compare against.
     *
     * @param string $unwanted case insenstive trimmed header name
     * @param string $message Optiona message override. Can use %s as
     *                        a placeholder for the original message.
     */
    public function __construct($unwanted, $message = '%s')
    {
        parent::__construct($unwanted, false, $message);
    }

    /**
     *    Tests that the unwanted header is not found.
     *
     * @param mixed $compare raw header block to search
     *
     * @return bool true if header present
     */
    public function test($compare)
    {
        return $this->findHeader($compare) === false;
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare raw header block to search
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $expectation = $this->getExpectation();
        if (is_string($line = $this->findHeader($compare))) {
            return "Found unwanted header [$expectation] with [$line]";
        } else {
            return "Did not find unwanted header [$expectation]";
        }
    }
}

/**
 *    Test for a text substring.
 */
class TextExpectation extends SimpleExpectation
{
    private $substring;

    /**
     *    Sets the value to compare against.
     *
     * @param string $substring text to search for
     * @param string $message customised message on failure
     */
    public function __construct($substring, $message = '%s')
    {
        parent::__construct($message);
        $this->substring = $substring;
    }

    /**
     *    Accessor for the substring.
     *
     * @return string text to match
     */
    protected function getSubstring()
    {
        return $this->substring;
    }

    /**
     *    Tests the expectation. True if the text contains the
     *    substring.
     *
     * @param string $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return strpos($compare, $this->substring) !== false;
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        if ($this->test($compare)) {
            return $this->describeTextMatch($this->getSubstring(), $compare);
        } else {
            $dumper = $this->getDumper();
            return 'Text [' . $this->getSubstring() .
            '] not detected in [' .
            $dumper->describeValue($compare) . ']';
        }
    }

    /**
     *    Describes a pattern match including the string
     *    found and it's position.
     *
     * @param string $substring text to search for
     * @param string $subject subject to search
     */
    protected function describeTextMatch($substring, $subject)
    {
        $position = strpos($subject, $substring);
        $dumper = $this->getDumper();
        return "Text [$substring] detected at character [$position] in [" .
        $dumper->describeValue($subject) . '] in region [' .
        $dumper->clipString($subject, 100, $position) . ']';
    }
}

/**
 *    Fail if a substring is detected within the
 *    comparison text.
 */
class NoTextExpectation extends TextExpectation
{
    /**
     *    Sets the reject pattern
     *
     * @param string $substring text to search for
     * @param string $message customised message on failure
     */
    public function __construct($substring, $message = '%s')
    {
        parent::__construct($substring, $message);
    }

    /**
     *    Tests the expectation. False if the substring appears
     *    in the text.
     *
     * @param string $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return !parent::test($compare);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param string $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        if ($this->test($compare)) {
            $dumper = $this->getDumper();
            return 'Text [' . $this->getSubstring() .
            '] not detected in [' .
            $dumper->describeValue($compare) . ']';
        } else {
            return $this->describeTextMatch($this->getSubstring(), $compare);
        }
    }
}

/**
 *    Test case for testing of web pages. Allows
 *    fetching of pages, parsing of HTML and
 *    submitting forms.
 */
class WebTestCase extends SimpleTestCase
{
    private $browser;
    private $ignore_errors = false;

    /**
     *    Creates an empty test case. Should be subclassed
     *    with test methods for a functional test case.
     *
     * @param string $label Name of test case. Will use
     *                      the class name if none specified.
     */
    public function __construct($label = false)
    {
        parent::__construct($label);
    }

    /**
     *    Announces the start of the test.
     *
     * @param string $method test method just started
     */
    public function before($method)
    {
        parent::before($method);
        $this->setBrowser($this->createBrowser());
    }

    /**
     *    Announces the end of the test. Includes private clean up.
     *
     * @param string $method test method just finished
     */
    public function after($method)
    {
        $this->unsetBrowser();
        parent::after($method);
    }

    /**
     *    Gets a current browser reference for setting
     *    special expectations or for detailed
     *    examination of page fetches.
     *
     * @return SimpleBrowser current test browser object
     */
    public function getBrowser()
    {
        return $this->browser;
    }

    /**
     *    Gets a current browser reference for setting
     *    special expectations or for detailed
     *    examination of page fetches.
     *
     * @param SimpleBrowser $browser new test browser object
     */
    public function setBrowser($browser)
    {
        return $this->browser = $browser;
    }

    /**
     *    Sets the HTML parser to use within this browser.
     *
     * @param object         the parser, one of SimplePHPPageBuilder or
     *                          SimpleTidyPageBuilder
     */
    public function setParser($parser)
    {
        $this->browser->setParser($parser);
    }

    /**
     *    Clears the current browser reference to help the
     *    PHP garbage collector.
     */
    public function unsetBrowser()
    {
        unset($this->browser);
    }

    /**
     *    Creates a new default web browser object.
     *    Will be cleared at the end of the test method.
     *
     * @return TestBrowser new browser
     */
    public function createBrowser()
    {
        return new SimpleBrowser();
    }

    /**
     *    Gets the last response error.
     *
     * @return string last low level HTTP error
     */
    public function getTransportError()
    {
        return $this->browser->getTransportError();
    }

    /**
     *    Accessor for the currently selected URL.
     *
     * @return string current location or false if
     *                no page yet fetched
     */
    public function getUrl()
    {
        return $this->browser->getUrl();
    }

    /**
     *    Dumps the current request for debugging.
     */
    public function showRequest()
    {
        $this->dump($this->browser->getRequest());
    }

    /**
     *    Dumps the current HTTP headers for debugging.
     */
    public function showHeaders()
    {
        $this->dump($this->browser->getHeaders());
    }

    /**
     *    Dumps the current HTML source for debugging.
     */
    public function showSource()
    {
        $this->dump($this->browser->getContent());
    }

    /**
     *    Dumps the visible text only for debugging.
     */
    public function showText()
    {
        $this->dump(wordwrap($this->browser->getContentAsText(), 80));
    }

    /**
     *    Simulates the closing and reopening of the browser.
     *    Temporary cookies will be discarded and timed
     *    cookies will be expired if later than the
     *    specified time.
     *
     * @param string /integer $date Time when session restarted.
     *                                If ommitted then all persistent
     *                                cookies are kept. Time is either
     *                                Cookie format string or timestamp.
     */
    public function restart($date = false)
    {
        if ($date === false) {
            $date = time();
        }
        $this->browser->restart($date);
    }

    /**
     *    Moves cookie expiry times back into the past.
     *    Useful for testing timeouts and expiries.
     *
     * @param int $interval amount to age in seconds
     */
    public function ageCookies($interval)
    {
        $this->browser->ageCookies($interval);
    }

    /**
     *    Disables frames support. Frames will not be fetched
     *    and the frameset page will be used instead.
     */
    public function ignoreFrames()
    {
        $this->browser->ignoreFrames();
    }

    /**
     *    Switches off cookie sending and recieving.
     */
    public function ignoreCookies()
    {
        $this->browser->ignoreCookies();
    }

    /**
     *    Skips errors for the next request only. You might
     *    want to confirm that a page is unreachable for
     *    example.
     */
    public function ignoreErrors()
    {
        $this->ignore_errors = true;
    }

    /**
     *    Issues a fail if there is a transport error anywhere
     *    in the current frameset. Only one such error is
     *    reported.
     *
     * @param string /boolean $result   HTML or failure
     *
     * @return string/boolean $result  Passes through result
     */
    protected function failOnError($result)
    {
        if (!$this->ignore_errors) {
            if ($error = $this->browser->getTransportError()) {
                $this->fail($error);
            }
        }
        $this->ignore_errors = false;
        return $result;
    }

    /**
     *    Adds a header to every fetch.
     *
     * @param string $header header line to add to every
     *                       request until cleared
     */
    public function addHeader($header)
    {
        $this->browser->addHeader($header);
    }

    /**
     *    Sets the maximum number of redirects before
     *    the web page is loaded regardless.
     *
     * @param int $max maximum hops
     */
    public function setMaximumRedirects($max)
    {
        if (!$this->browser) {
            trigger_error(
                'Can only set maximum redirects in a test method, setUp() or tearDown()');
        }
        $this->browser->setMaximumRedirects($max);
    }

    /**
     *    Sets the socket timeout for opening a connection and
     *    receiving at least one byte of information.
     *
     * @param int $timeout maximum time in seconds
     */
    public function setConnectionTimeout($timeout)
    {
        $this->browser->setConnectionTimeout($timeout);
    }

    /**
     *    Sets proxy to use on all requests for when
     *    testing from behind a firewall. Set URL
     *    to false to disable.
     *
     * @param string $proxy proxy URL
     * @param string $username proxy username for authentication
     * @param string $password proxy password for authentication
     */
    public function useProxy($proxy, $username = false, $password = false)
    {
        $this->browser->useProxy($proxy, $username, $password);
    }

    /**
     *    Fetches a page into the page buffer. If
     *    there is no base for the URL then the
     *    current base URL is used. After the fetch
     *    the base URL reflects the new location.
     *
     * @param string $url URL to fetch
     * @param hash $parameters optional additional GET data
     *
     * @return boolean/string      Raw page on success
     */
    public function get($url, $parameters = false)
    {
        return $this->failOnError($this->browser->get($url, $parameters));
    }

    /**
     *    Fetches a page by POST into the page buffer.
     *    If there is no base for the URL then the
     *    current base URL is used. After the fetch
     *    the base URL reflects the new location.
     *
     * @param string $url URL to fetch
     * @param mixed $parameters Optional POST parameters or content body to send
     * @param string $content_type Content type of provided body
     *
     * @return boolean/string      Raw page on success
     */
    public function post($url, $parameters = false, $content_type = false)
    {
        return $this->failOnError($this->browser->post($url, $parameters, $content_type));
    }

    /**
     *    Fetches a page by PUT into the page buffer.
     *    If there is no base for the URL then the
     *    current base URL is used. After the fetch
     *    the base URL reflects the new location.
     *
     * @param string $url URL to fetch
     * @param mixed $body Optional content body to send
     * @param string $content_type Content type of provided body
     *
     * @return boolean/string      Raw page on success
     */
    public function put($url, $body = false, $content_type = false)
    {
        return $this->failOnError($this->browser->put($url, $body, $content_type));
    }

    /**
     *    Fetches a page by a DELETE request
     *
     * @param string $url URL to fetch
     * @param hash $parameters optional additional parameters
     *
     * @return boolean/string      Raw page on success
     */
    public function delete($url, $parameters = false)
    {
        return $this->failOnError($this->browser->delete($url, $parameters));
    }

    /**
     *    Does a HTTP HEAD fetch, fetching only the page
     *    headers. The current base URL is unchanged by this.
     *
     * @param string $url URL to fetch
     * @param hash $parameters optional additional GET data
     *
     * @return bool true on success
     */
    public function head($url, $parameters = false)
    {
        return $this->failOnError($this->browser->head($url, $parameters));
    }

    /**
     *    Equivalent to hitting the retry button on the
     *    browser. Will attempt to repeat the page fetch.
     *
     * @return bool true if fetch succeeded
     */
    public function retry()
    {
        return $this->failOnError($this->browser->retry());
    }

    /**
     *    Equivalent to hitting the back button on the
     *    browser.
     *
     * @return bool true if history entry and
     *              fetch succeeded
     */
    public function back()
    {
        return $this->failOnError($this->browser->back());
    }

    /**
     *    Equivalent to hitting the forward button on the
     *    browser.
     *
     * @return bool true if history entry and
     *              fetch succeeded
     */
    public function forward()
    {
        return $this->failOnError($this->browser->forward());
    }

    /**
     *    Retries a request after setting the authentication
     *    for the current realm.
     *
     * @param string $username username for realm
     * @param string $password password for realm
     *
     * @return boolean/string     HTML on successful fetch. Note
     *                               that authentication may still have
     *                               failed.
     */
    public function authenticate($username, $password)
    {
        return $this->failOnError(
            $this->browser->authenticate($username, $password));
    }

    /**
     *    Gets the cookie value for the current browser context.
     *
     * @param string $name name of cookie
     *
     * @return string value of cookie or false if unset
     */
    public function getCookie($name)
    {
        return $this->browser->getCurrentCookieValue($name);
    }

    /**
     *    Sets a cookie in the current browser.
     *
     * @param string $name name of cookie
     * @param string $value cookie value
     * @param string $host host upon which the cookie is valid
     * @param string $path cookie path if not host wide
     * @param string $expiry expiry date
     */
    public function setCookie($name, $value, $host = false, $path = '/', $expiry = false)
    {
        $this->browser->setCookie($name, $value, $host, $path, $expiry);
    }

    /**
     *    Accessor for current frame focus. Will be
     *    false if no frame has focus.
     *
     * @return integer/string/boolean    Label if any, otherwise
     *                                      the position in the frameset
     *                                      or false if none
     */
    public function getFrameFocus()
    {
        return $this->browser->getFrameFocus();
    }

    /**
     *    Sets the focus by index. The integer index starts from 1.
     *
     * @param int $choice chosen frame
     *
     * @return bool true if frame exists
     */
    public function setFrameFocusByIndex($choice)
    {
        return $this->browser->setFrameFocusByIndex($choice);
    }

    /**
     *    Sets the focus by name.
     *
     * @param string $name chosen frame
     *
     * @return bool true if frame exists
     */
    public function setFrameFocus($name)
    {
        return $this->browser->setFrameFocus($name);
    }

    /**
     *    Clears the frame focus. All frames will be searched
     *    for content.
     */
    public function clearFrameFocus()
    {
        return $this->browser->clearFrameFocus();
    }

    /**
     *    Clicks a visible text item. Will first try buttons,
     *    then links and then images.
     *
     * @param string $label visible text or alt text
     *
     * @return string/boolean      Raw page or false
     */
    public function click($label)
    {
        return $this->failOnError($this->browser->click($label));
    }

    /**
     *    Checks for a click target.
     *
     * @param string $label visible text or alt text
     *
     * @return bool true if click target
     */
    public function assertClickable($label, $message = '%s')
    {
        return $this->assertTrue(
            $this->browser->isClickable($label),
            sprintf($message, "Click target [$label] should exist"));
    }

    /**
     *    Clicks the submit button by label. The owning
     *    form will be submitted by this.
     *
     * @param string $label Button label. An unlabeled
     *                      button can be triggered by 'Submit'.
     * @param hash $additional additional form values
     *
     * @return boolean/string  Page on success, else false
     */
    public function clickSubmit($label = 'Submit', $additional = false)
    {
        return $this->failOnError(
            $this->browser->clickSubmit($label, $additional));
    }

    /**
     *    Clicks the submit button by name attribute. The owning
     *    form will be submitted by this.
     *
     * @param string $name name attribute of button
     * @param hash $additional additional form values
     *
     * @return boolean/string  Page on success
     */
    public function clickSubmitByName($name, $additional = false)
    {
        return $this->failOnError(
            $this->browser->clickSubmitByName($name, $additional));
    }

    /**
     *    Clicks the submit button by ID attribute. The owning
     *    form will be submitted by this.
     *
     * @param string $id ID attribute of button
     * @param hash $additional additional form values
     *
     * @return boolean/string  Page on success
     */
    public function clickSubmitById($id, $additional = false)
    {
        return $this->failOnError(
            $this->browser->clickSubmitById($id, $additional));
    }

    /**
     *    Checks for a valid button label.
     *
     * @param string $label visible text
     *
     * @return bool true if click target
     */
    public function assertSubmit($label, $message = '%s')
    {
        return $this->assertTrue(
            $this->browser->isSubmit($label),
            sprintf($message, "Submit button [$label] should exist"));
    }

    /**
     *    Clicks the submit image by some kind of label. Usually
     *    the alt tag or the nearest equivalent. The owning
     *    form will be submitted by this. Clicking outside of
     *    the boundary of the coordinates will result in
     *    a failure.
     *
     * @param string $label alt attribute of button
     * @param int $x X-coordinate of imaginary click
     * @param int $y Y-coordinate of imaginary click
     * @param hash $additional additional form values
     *
     * @return boolean/string  Page on success
     */
    public function clickImage($label, $x = 1, $y = 1, $additional = false)
    {
        return $this->failOnError(
            $this->browser->clickImage($label, $x, $y, $additional));
    }

    /**
     *    Clicks the submit image by the name. Usually
     *    the alt tag or the nearest equivalent. The owning
     *    form will be submitted by this. Clicking outside of
     *    the boundary of the coordinates will result in
     *    a failure.
     *
     * @param string $name name attribute of button
     * @param int $x X-coordinate of imaginary click
     * @param int $y Y-coordinate of imaginary click
     * @param hash $additional additional form values
     *
     * @return boolean/string  Page on success
     */
    public function clickImageByName($name, $x = 1, $y = 1, $additional = false)
    {
        return $this->failOnError(
            $this->browser->clickImageByName($name, $x, $y, $additional));
    }

    /**
     *    Clicks the submit image by ID attribute. The owning
     *    form will be submitted by this. Clicking outside of
     *    the boundary of the coordinates will result in
     *    a failure.
     *
     * @param int /string $id   ID attribute of button
     * @param int $x X-coordinate of imaginary click
     * @param int $y Y-coordinate of imaginary click
     * @param hash $additional additional form values
     *
     * @return boolean/string      Page on success
     */
    public function clickImageById($id, $x = 1, $y = 1, $additional = false)
    {
        return $this->failOnError(
            $this->browser->clickImageById($id, $x, $y, $additional));
    }

    /**
     *    Checks for a valid image with atht alt text or title.
     *
     * @param string $label visible text
     *
     * @return bool true if click target
     */
    public function assertImage($label, $message = '%s')
    {
        return $this->assertTrue(
            $this->browser->isImage($label),
            sprintf($message, "Image with text [$label] should exist"));
    }

    /**
     *    Submits a form by the ID.
     *
     * @param string $id Form ID. No button information
     *                   is submitted this way.
     *
     * @return boolean/string  Page on success
     */
    public function submitFormById($id, $additional = false)
    {
        return $this->failOnError($this->browser->submitFormById($id, $additional));
    }

    /**
     *    Follows a link by name. Will click the first link
     *    found with this link text by default, or a later
     *    one if an index is given. Match is case insensitive
     *    with normalised space.
     *
     * @param string $label text between the anchor tags
     * @param int $index link position counting from zero
     *
     * @return boolean/string   Page on success
     */
    public function clickLink($label, $index = 0)
    {
        return $this->failOnError($this->browser->clickLink($label, $index));
    }

    /**
     *    Follows a link by id attribute.
     *
     * @param string $id ID attribute value
     *
     * @return boolean/string   Page on success
     */
    public function clickLinkById($id)
    {
        return $this->failOnError($this->browser->clickLinkById($id));
    }

    /**
     *    Tests for the presence of a link label. Match is
     *    case insensitive with normalised space.
     *
     * @param string $label text between the anchor tags
     * @param mixed $expected expected URL or expectation object
     * @param string $message Message to display. Default
     *                        can be embedded with %s.
     *
     * @return bool true if link present
     */
    public function assertLink($label, $expected = true, $message = '%s')
    {
        $url = $this->browser->getLink($label);
        if ($expected === true || ($expected !== true && $url === false)) {
            return $this->assertTrue($url !== false, sprintf($message, "Link [$label] should exist"));
        }
        if (!SimpleExpectation::isExpectation($expected)) {
            $expected = new IdenticalExpectation($expected);
        }
        return $this->assert($expected, $url->asString(), sprintf($message, "Link [$label] should match"));
    }

    /**
     *    Tests for the non-presence of a link label. Match is
     *    case insensitive with normalised space.
     *
     * @param string /integer $label    Text between the anchor tags
     *                                    or ID attribute
     * @param string $message Message to display. Default
     *                        can be embedded with %s.
     *
     * @return bool true if link missing
     */
    public function assertNoLink($label, $message = '%s')
    {
        return $this->assertTrue(
            $this->browser->getLink($label) === false,
            sprintf($message, "Link [$label] should not exist"));
    }

    /**
     *    Tests for the presence of a link id attribute.
     *
     * @param string $id id attribute value
     * @param mixed $expected expected URL or expectation object
     * @param string $message Message to display. Default
     *                        can be embedded with %s.
     *
     * @return bool true if link present
     */
    public function assertLinkById($id, $expected = true, $message = '%s')
    {
        $url = $this->browser->getLinkById($id);
        if ($expected === true) {
            return $this->assertTrue($url !== false, sprintf($message, "Link ID [$id] should exist"));
        }
        if (!SimpleExpectation::isExpectation($expected)) {
            $expected = new IdenticalExpectation($expected);
        }
        return $this->assert($expected, $url->asString(), sprintf($message, "Link ID [$id] should match"));
    }

    /**
     *    Tests for the non-presence of a link label. Match is
     *    case insensitive with normalised space.
     *
     * @param string $id id attribute value
     * @param string $message Message to display. Default
     *                        can be embedded with %s.
     *
     * @return bool true if link missing
     */
    public function assertNoLinkById($id, $message = '%s')
    {
        return $this->assertTrue(
            $this->browser->getLinkById($id) === false,
            sprintf($message, "Link ID [$id] should not exist"));
    }

    /**
     *    Sets all form fields with that label, or name if there
     *    is no label attached.
     *
     * @param string $value new value of field
     *
     * @return bool true if field exists, otherwise false
     */
    public function setField($label, $value, $position = false)
    {
        return $this->browser->setField($label, $value, $position);
    }

    /**
     *    Sets all form fields with that name.
     *
     * @param string $name name of field in forms
     * @param string $value new value of field
     *
     * @return bool true if field exists, otherwise false
     */
    public function setFieldByName($name, $value, $position = false)
    {
        return $this->browser->setFieldByName($name, $value, $position);
    }

    /**
     *    Sets all form fields with that id.
     *
     * @param string /integer $id   Id of field in forms
     * @param string $value new value of field
     *
     * @return bool true if field exists, otherwise false
     */
    public function setFieldById($id, $value)
    {
        return $this->browser->setFieldById($id, $value);
    }

    /**
     *    Confirms that the form element is currently set
     *    to the expected value. A missing form will always
     *    fail. If no value is given then only the existence
     *    of the field is checked.
     *
     * @param mixed $expected expected string/array value or
     *                        false for unset fields
     * @param string $message Message to display. Default
     *                        can be embedded with %s.
     *
     * @return bool true if pass
     */
    public function assertField($label, $expected = true, $message = '%s')
    {
        $value = $this->browser->getField($label);
        return $this->assertFieldValue($label, $value, $expected, $message);
    }

    /**
     *    Confirms that the form element is currently set
     *    to the expected value. A missing form element will always
     *    fail. If no value is given then only the existence
     *    of the field is checked.
     *
     * @param string $name name of field in forms
     * @param mixed $expected expected string/array value or
     *                        false for unset fields
     * @param string $message Message to display. Default
     *                        can be embedded with %s.
     *
     * @return bool true if pass
     */
    public function assertFieldByName($name, $expected = true, $message = '%s')
    {
        $value = $this->browser->getFieldByName($name);
        return $this->assertFieldValue($name, $value, $expected, $message);
    }

    /**
     *    Confirms that the form element is currently set
     *    to the expected value. A missing form will always
     *    fail. If no ID is given then only the existence
     *    of the field is checked.
     *
     * @param string /integer $id  Name of field in forms
     * @param mixed $expected expected string/array value or
     *                        false for unset fields
     * @param string $message Message to display. Default
     *                        can be embedded with %s.
     *
     * @return bool true if pass
     */
    public function assertFieldById($id, $expected = true, $message = '%s')
    {
        $value = $this->browser->getFieldById($id);
        return $this->assertFieldValue($id, $value, $expected, $message);
    }

    /**
     *    Tests the field value against the expectation.
     *
     * @param string $identifier name, ID or label
     * @param mixed $value current field value
     * @param mixed $expected expected value to match
     * @param string $message failure message
     *
     * @return bool True if pass
     */
    protected function assertFieldValue($identifier, $value, $expected, $message)
    {
        if ($expected === true) {
            return $this->assertTrue(
                isset($value),
                sprintf($message, "Field [$identifier] should exist"));
        }
        if (!SimpleExpectation::isExpectation($expected)) {
            $identifier = str_replace('%', '%%', $identifier);
            $expected = new FieldExpectation(
                $expected,
                "Field [$identifier] should match with [%s]");
        }
        return $this->assert($expected, $value, $message);
    }

    /**
     *    Checks the response code against a list
     *    of possible values.
     *
     * @param array $responses possible responses for a pass
     * @param string $message Message to display. Default
     *                        can be embedded with %s.
     *
     * @return bool true if pass
     */
    public function assertResponse($responses, $message = '%s')
    {
        $responses = (is_array($responses) ? $responses : [$responses]);
        $code = $this->browser->getResponseCode();
        try {
            $message = sprintf($message, 'Expecting response in [' .
                implode(', ', $responses) . "] got [$code]");
        } catch (Throwable $e) {
            throw new Exception(var_export($message, true));
        }
        return $this->assertTrue(in_array($code, $responses), $message);
    }

    /**
     *    Checks the mime type against a list
     *    of possible values.
     *
     * @param array $types possible mime types for a pass
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertMime($types, $message = '%s')
    {
        $types = (is_array($types) ? $types : [$types]);
        $type = $this->browser->getMimeType();
        $message = sprintf($message, 'Expecting mime type in [' .
            implode(', ', $types) . "] got [$type]");
        return $this->assertTrue(in_array($type, $types), $message);
    }

    /**
     *    Attempt to match the authentication type within
     *    the security realm we are currently matching.
     *
     * @param string $authentication usually basic
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertAuthentication($authentication = false, $message = '%s')
    {
        if (!$authentication) {
            $message = sprintf($message, 'Expected any authentication type, got [' .
                $this->browser->getAuthentication() . ']');
            return $this->assertTrue(
                $this->browser->getAuthentication(),
                $message);
        } else {
            $message = sprintf($message, "Expected authentication [$authentication] got [" .
                $this->browser->getAuthentication() . ']');
            return $this->assertTrue(
                strtolower($this->browser->getAuthentication()) == strtolower($authentication),
                $message);
        }
    }

    /**
     *    Checks that no authentication is necessary to view
     *    the desired page.
     *
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertNoAuthentication($message = '%s')
    {
        $message = sprintf($message, 'Expected no authentication type, got [' .
            $this->browser->getAuthentication() . ']');
        return $this->assertFalse($this->browser->getAuthentication(), $message);
    }

    /**
     *    Attempts to match the current security realm.
     *
     * @param string $realm name of security realm
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertRealm($realm, $message = '%s')
    {
        if (!SimpleExpectation::isExpectation($realm)) {
            $realm = new EqualExpectation($realm);
        }
        return $this->assert(
            $realm,
            $this->browser->getRealm(),
            "Expected realm -> $message");
    }

    /**
     *    Checks each header line for the required value. If no
     *    value is given then only an existence check is made.
     *
     * @param string $header case insensitive header name
     * @param mixed $value Case sensitive trimmed string to
     *                     match against. An expectation object
     *                     can be used for pattern matching.
     *
     * @return bool true if pass
     */
    public function assertHeader($header, $value = false, $message = '%s')
    {
        return $this->assert(
            new HttpHeaderExpectation($header, $value),
            $this->browser->getHeaders(),
            $message);
    }

    /**
     *    Confirms that the header type has not been received.
     *    Only the landing page is checked. If you want to check
     *    redirect pages, then you should limit redirects so
     *    as to capture the page you want.
     *
     * @param string $header case insensitive header name
     *
     * @return bool true if pass
     */
    public function assertNoHeader($header, $message = '%s')
    {
        return $this->assert(
            new NoHttpHeaderExpectation($header),
            $this->browser->getHeaders(),
            $message);
    }

    /**
     *    Tests the text between the title tags.
     *
     * @param string /SimpleExpectation $title    Expected title
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertTitle($title = false, $message = '%s')
    {
        if (!SimpleExpectation::isExpectation($title)) {
            $title = new EqualExpectation($title);
        }
        return $this->assert($title, $this->browser->getTitle(), $message);
    }

    /**
     *    Will trigger a pass if the text is found in the plain
     *    text form of the page.
     *
     * @param string $text text to look for
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertText($text, $message = '%s')
    {
        return $this->assert(
            new TextExpectation($text),
            $this->browser->getContentAsText(),
            $message);
    }

    /**
     *    Will trigger a pass if the text is not found in the plain
     *    text form of the page.
     *
     * @param string $text text to look for
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertNoText($text, $message = '%s')
    {
        return $this->assert(
            new NoTextExpectation($text),
            $this->browser->getContentAsText(),
            $message);
    }

    /**
     *    Will trigger a pass if the Perl regex pattern
     *    is found in the raw content.
     *
     * @param string $pattern perl regex to look for including
     *                        the regex delimiters
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertPattern($pattern, $message = '%s')
    {
        return $this->assert(
            new PatternExpectation($pattern),
            $this->browser->getContent(),
            $message);
    }

    /**
     *    Will trigger a pass if the perl regex pattern
     *    is not present in raw content.
     *
     * @param string $pattern perl regex to look for including
     *                        the regex delimiters
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertNoPattern($pattern, $message = '%s')
    {
        return $this->assert(
            new NoPatternExpectation($pattern),
            $this->browser->getContent(),
            $message);
    }

    /**
     *    Checks that a cookie is set for the current page
     *    and optionally checks the value.
     *
     * @param string $name name of cookie to test
     * @param string $expected expected value as a string or
     *                         false if any value will do
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertCookie($name, $expected = false, $message = '%s')
    {
        $value = $this->getCookie($name);
        if (!$expected) {
            return $this->assertTrue(
                $value,
                sprintf($message, "Expecting cookie [$name]"));
        }
        if (!SimpleExpectation::isExpectation($expected)) {
            $expected = new EqualExpectation($expected);
        }
        return $this->assert($expected, $value, "Expecting cookie [$name] -> $message");
    }

    /**
     *    Checks that no cookie is present or that it has
     *    been successfully cleared.
     *
     * @param string $name name of cookie to test
     * @param string $message message to display
     *
     * @return bool true if pass
     */
    public function assertNoCookie($name, $message = '%s')
    {
        return $this->assertTrue(
            $this->getCookie($name) === null or $this->getCookie($name) === false,
            sprintf($message, "Not expecting cookie [$name]"));
    }

    /**
     *    Called from within the test methods to register
     *    passes and failures.
     *
     * @param bool $result pass on true
     * @param string $message message to display describing
     *                        the test state
     *
     * @return bool True on pass
     */
    public function assertTrue($result, $message = '%s')
    {
        return $this->assert(new TrueExpectation(), $result, $message);
    }

    /**
     *    Will be true on false and vice versa. False
     *    is the PHP definition of false, so that null,
     *    empty strings, zero and an empty array all count
     *    as false.
     *
     * @param bool $result pass on false
     * @param string $message message to display
     *
     * @return bool True on pass
     */
    public function assertFalse($result, $message = '%s')
    {
        return $this->assert(new FalseExpectation(), $result, $message);
    }

    /**
     *    Will trigger a pass if the two parameters have
     *    the same value only. Otherwise a fail. This
     *    is for testing hand extracted text, etc.
     *
     * @param mixed $first value to compare
     * @param mixed $second value to compare
     * @param string $message message to display
     *
     * @return bool True on pass
     */
    public function assertEqual($first, $second, $message = '%s')
    {
        return $this->assert(
            new EqualExpectation($first),
            $second,
            $message);
    }

    /**
     *    Will trigger a pass if the two parameters have
     *    a different value. Otherwise a fail. This
     *    is for testing hand extracted text, etc.
     *
     * @param mixed $first value to compare
     * @param mixed $second value to compare
     * @param string $message message to display
     *
     * @return bool True on pass
     */
    public function assertNotEqual($first, $second, $message = '%s')
    {
        return $this->assert(
            new NotEqualExpectation($first),
            $second,
            $message);
    }

    /**
     *    Uses a stack trace to find the line of an assertion.
     *
     * @return string line number of first assert*
     *                method embedded in format string
     */
    public function getAssertionLine()
    {
        $trace = new SimpleStackTrace(['assert', 'click', 'pass', 'fail']);
        return $trace->traceMethod();
    }
}
