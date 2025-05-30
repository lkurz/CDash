<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class PubProjectTestCase extends KWWebTestCase
{
    protected $ProjectId;

    public function __construct()
    {
        parent::__construct();
        $this->ProjectId = -1;
    }

    public function testCreateProject()
    {
        $settings = [
            'Name' => 'ProjectTest',
            'Description' => 'This is a project test for cdash',
        ];
        $this->ProjectId = $this->createProject($settings);
    }

    public function testProjectTestInDatabase()
    {
        $query = "SELECT name,description,public FROM project WHERE name = 'ProjectTest'";
        $result = $this->db->query($query);
        $nameexpected = 'ProjectTest';
        $descriptionexpected = 'This is a project test for cdash';
        $publicexpected = 1;
        $expected = [
            'name' => $nameexpected,
            'description' => $descriptionexpected,
            'public' => $publicexpected];

        $this->assertEqual($result[0], $expected);
    }

    public function testIndexProjectTest()
    {
        $content = $this->get($this->url . '/api/v1/index.php?project=ProjectTest');
        if (!str_contains($content, 'ProjectTest')) {
            $this->fail('"ProjectTest" not found when expected');
        }
    }

    public function testEditProject()
    {
        $content = $this->get($this->url . "/project/$this->ProjectId/ctest_configuration");
        $expected = '## This file should be placed in the root directory of your project.';
        if (!$this->findString($content, $expected)) {
            $this->assertText($content, $expected);
        }

        $this->deleteProject($this->ProjectId);
    }
}
