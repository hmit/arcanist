<?php

/**
 * UberTestPlanIOSAndroidTestEngine
 *
 * Enforces a test plan like:
 *
 * Test Plan:
 * ios: abc, def
 * android: def, xyz
 *
 */
final class UberTestPlanIOSAndroidTestEngine extends ArcanistUnitTestEngine {

    public function run() {
        return $this->checkNonEmptyTestPlan();
    }

    // Checks the git commit log and the arcanist message cache for a test plan
    private function checkNonEmptyTestPlan() {
        $result = new ArcanistUnitTestResult();
        $result->setName("Test Plan");

        $lines = join(' ', $this->getMessage());
        $testPlanExists = preg_match('/\sTest Plan:/', $lines);

        if (!$testPlanExists) {
            $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
            print 'Test Plan not found!';
            return array($result);
        }

        $platforms = $this->getPlatformTestsFromLines($this->getMessage());
        if ($platforms) {
            print "Found tests for the following platforms:\n";
            foreach ($platforms as $platform => $tests) {
                print "$platform:\n";
                print join("\t\n", $tests);
                print "\n";
            }
            $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
            print 'Test Plan found!';
            return array($result);    
        }

        $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
        print "No tests found to run on CI! Check your repo's README for instructions\n";
        return array($result);
        
    }

    private function getMessage() {
        $message_file = ".git/arc/create-message";
        exec('git log `git merge-base origin/master HEAD`^..HEAD --oneline', $output, $return_code);
        $commit = count($output) - 1;
        exec("git log -n {$commit}", $output, $return_code);

        if (file_exists($message_file)) {
            $message = file_get_contents(".git/arc/create-message");
            array_push($output, $message);
        }

        return $output;
    }

    private function getPlatformTestsFromLines($lines) {
        $platforms = array(
            'ios' => array(), 
            'android' => array()
        );
        $candidates = $this->getCandidateLines($lines);
        foreach ($candidates as $candidate) {
            $pieces = explode(":", $candidate, 2);
            $platform = strtolower($pieces[0]);
            if (in_array($platform, array_keys($platforms))) {
                $platforms[$platform] = array_merge($platforms[$platform], explode(',', $pieces[1]));
            }
        }
        foreach ($platforms as $key => $value) {
            if (count($value) == 0) {
                unset($platforms[$key]);
            }
        }
        return $platforms;
    }

    private function getCandidateLines($lines) {
        $start = "Test Plan:";
        $end = "";
        $shouldProcess = false;
        $lines_to_parse = array();
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($shouldProcess) {
                if (strcmp($trimmed, $end) == 0) {
                    $shouldProcess = false;
                } else {
                    array_push($lines_to_parse, $trimmed);
                }
            } else {
                if (strcmp($trimmed, $start) == 0) {
                    $shouldProcess = true;
                }
            }
        }
        return $lines_to_parse;
    }

}

?>
