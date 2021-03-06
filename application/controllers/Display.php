<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * controllers/Display.php
 *
 * Present an activity as a webpage.
 * This would be normal handling for an MD or RST document.
 *
 * ------------------------------------------------------------------------
 */
class Display extends Application {

    function __construct() {
        parent::__construct();
        $course = $this->course->metadata();
        $this->data = array_merge($this->data, (array) $course);
    }

    //-------------------------------------------------------------
    //  Default entry point
    //-------------------------------------------------------------

    function index() {
        // We should never enter here
        redirect('/');
    }

    /**
     * Do the right thing
     */
    private function work($category, $name) {
        if ($name == "#") {
            redirect($_SERVER['HTTP_REFERER']);
        }

        $activity = $this->organizer->activity($category, $name);
        if ($activity == null) {
            redirect('/');
        }

        // handle the type of activity
        switch ($activity->type) {
            // some document kinds are not rendered
            case 'video':
            case 'github':
                // these shouldn't have come here, but if they did...
                redirect($activity->link);
                break;
            case 'example':
                // these shouldn't have come here, but if they did...
                $this->_serve($activity->link);
                break;
            case 'quiz':
                // hmm - what should we do for this?
                redirect('/');
                break;
            default:
                $kind = $this->organizer->kind($activity->type, $activity->name);
                switch ($kind) {
                    case 'md':
                        $data = file_get_contents(DATAPATH . $category . 's/' . $activity->name . '.' . $kind);
                        $result = $this->parsedown->text($data);
                        $this->data['content'] = $this->parser->parse_string($result, $this->data, true);
                        break;
//					case 'rst':
//						break;
                    case 'xml':
                        $this->data['content'] = $this->slideshow($activity);
                        break;
                    default:
                        // we give up ... show it as text!
                        $filename = DATAPATH . $activity->category . 's/' . $activity->name . '.' . $kind;
                        $this->data['content'] = htmlentities(file_get_contents($filename));
                }
                $this->template = 'theme/standalone';
                $this->render(); // and away we go!
        }
    }

    /**
     * Entry point for a lesson
     */
    public function lesson($which = "#") {
        $this->work('lesson', $which);
    }

    /**
     * Entry point for an example
     */
    public function example($which = "#") {
        $this->_serve('download/' . $which);
    }

    /**
     * Entry point for a repo
     */
    public function github($which = "#") {
        $this->work('github', $which);
    }

    /**
     * Entry point for a tutorial
     */
    public function tutorial($which = "#") {
        $this->work('tutorial', $which);
    }

    /**
     * Entry point for a lab
     */
    public function lab($which = "#") {
        $this->work('lab', $which);
    }

    /**
     * Entry point for an assignment
     */
    public function assignment($which = "#") {
        $this->work('assignment', $which);
    }

    /**
     * Entry point for a exam
     */
    public function exam($which = "#") {
        $this->work('exam', $which);
    }

    /**
     * Entry point for an existing PDF document
     */
    public function pdf($which = "#") {
        if ($which != "#")
            $this->_serve(DATAPATH . 'pdf/' . $which);
        else
            redirect('/');
    }

    /**
     * Serve a file
     */
    private function _serve($source) {
        if (!file_exists($source)) {
            show_404($source);
        }
        $this->load->helper('file');
        $mimeType = get_mime_by_extension($source);
        header("Content-type: " . $mimeType);
        header('Content-Disposition: inline');
        readfile($source);
    }

    /**
     * Build the contents of a slideshow for plain webpage rendering
     */
    private function slideshow($activity) {
        $course = $this->course->metadata();
        $this->data = array_merge($this->data, (array) $course);
        $this->data = array_merge($this->data, (array) $activity);
        $this->data = array_merge($this->data, $this->organizer->tags($activity));
        $this->data['status'] = '?'; // questionable status to begin
        // figure out the followup
        $following = $this->organizer->followup($activity);
        if ($following == null)
            $this->data['followup'] = 'There is nothing further in this course';
        else
            $this->data['followup'] = $this->parser->parse('show/_activity', (array) $following, true);

        // start this slideshow with a title slide
        $this->data['intro_slide'] = $this->parser->parse('show/_title_slide', $this->data, true);

        // Now generate the "real" slides
        $result = '';

        // but only if the data comes from an XML document, assumed to be S5-formatted
        $filename = DATAPATH . $activity->category . 's/' . $activity->name . '.xml';
        if (file_exists($filename)) {
            $xml = simplexml_load_file($filename);
            $this->data['status'] = (string) $xml['status']; // update if there

            foreach ($xml->slide as $slide) {
                // pre-parsing
                $body = $this->parser->parse_string((string) $slide->asXML(), $this->data, true);

                // slide parsing
                $parms = array(
                    'title' => (string) $slide['title'],
                    'layout' => (string) $slide['layout'],
                    'body' => $body,
                );
                $result .= $this->parser->parse('show/_slide', $parms, true);
            }
        }
        $this->data['content'] = $result;

        $this->data['congrats'] = $this->parser->parse('show/_congrats', $this->data, true);

        //$this->data['pagetitle'] = $this->data['title'];
        return $this->parser->parse('theme/template_2', $this->data, true);
    }

}
