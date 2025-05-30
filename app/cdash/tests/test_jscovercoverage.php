<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class JSCoverCoverageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testJSCoverCoverage()
    {
        // Do the POST submission to get a pending buildid.
        $post_result = $this->post($this->url . '/submit.php', [
            'project' => 'SubProjectExample',
            'build' => 'jscover_coverage',
            'site' => 'localhost',
            'stamp' => '20150128-1436-Experimental',
            'starttime' => '1422455768',
            'endtime' => '1422455768',
            'track' => 'Experimental',
            'type' => 'JSCoverTar',
            'datafilesmd5[0]=' => 'e99bdd400ab4643e4fbeef7ec649f04e']);

        $post_json = json_decode($post_result, true);
        if ($post_json['status'] != 0) {
            $this->fail(
                'POST returned ' . $post_json['status'] . ":\n" .
                $post_json['description'] . "\n");
            return 1;
        }

        $buildid = $post_json['buildid'];
        if (!is_numeric($buildid) || $buildid < 1) {
            $this->fail(
                "Expected positive integer for buildid, instead got $buildid");
            return 1;
        }

        // Do the PUT submission to actually upload our data.
        $puturl = $this->url . "/submit.php?type=JSCoverTar&md5=e99bdd400ab4643e4fbeef7ec649f04e&filename=JSCoverTest.tar&buildid=$buildid";
        $filename = dirname(__FILE__) . '/data/JSCoverTest.tar';

        $put_result = $this->uploadfile($puturl, $filename);
        if (!str_contains($put_result, '{"status":0}')) {
            $this->fail(
                "status:0 not found in PUT results:\n$put_result\n");
            return 1;
        }

        // Verify that the coverage data was successfully parsed.
        $content = $this->get(
            $this->url . "/viewCoverage.php?buildid=$buildid&status=6");
        if (!str_contains($content, '86.06')) {
            $this->fail('\"86.06\" not found when expected');
            return 1;
        }
        return 0;
    }
}
