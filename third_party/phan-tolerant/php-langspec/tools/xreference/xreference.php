<?php

function get_section_map($section_map_file) {
  $handle = fopen($section_map_file, "r");
  $section_map = array();
  while (($pair = fgetcsv($handle)) !== false) {
    // section number is the key, title is the value
    // remove spaces from title, replace inner spaces with empty string
    $section_map[$pair[0]] = str_replace(" ", "", trim($pair[1]));
  }
  return $section_map;
}

function insert_cross_references($md_file, $section_map, $ref_link_text = NULL,
                                $update_only = false) {
  $contents = file($md_file);
  $update = "";
  $pattern = "/§[\[]?[A-Z0-9.]+[\]]?/";
  $matches = array();
  foreach ($contents as $line) {
    if (preg_match_all($pattern, $line, $matches) > 0) {
      // Get the entire match array. Should be the only one there anyway.
      $matches = $matches[0];
      foreach ($matches as $match) {
        // Get rid of any trailing . in case the cross reference is the
        // end of a sentence and the match occurred.
        $match = rtrim($match, ".");
        // For each cross reference match we found, replace with
        // markdown link in our map and create a text link to the title
        $text = $ref_link_text === null ? $match : $ref_link_text;
        $replace = "[" . $text . "]";
        if (!$update_only){
          $replace .= "(#" . $section_map[$match] . ")";
        }
        $line = str_replace($match, $replace, $line);
      }
    }
    $update .= $line;
  }
  file_put_contents($md_file, $update);
}


function map_word_sections_to_markdown_sections($section_map_file, $md_file) {
  $md_contents = file_get_contents($md_file);
  $toc_start = strpos($md_contents, "**Table of Contents**");
  $toc_end = strpos($md_contents, "#Introduction");
  $md_toc = substr($md_contents, $toc_start, $toc_end);
  $md_section_pattern = "/\(#([a-z0-9\-_]+)\)/";
  $matches = array();
  preg_match_all($md_section_pattern, $md_toc, $matches);
  // Just want the sub-match only
  $matches = $matches[1];
  $sm_contents = file($section_map_file, FILE_IGNORE_NEW_LINES);
  $num_elements = count($sm_contents);
  for ($i = 0; $i < $num_elements; $i++) {
    if (!strpos($sm_contents[$i], $matches[$i])) {
      $sm_contents[$i] .= "," . $matches[$i];
    }
  }
  file_put_contents($section_map_file, implode(PHP_EOL, $sm_contents));
}

function main($argv) {
  $opts = getopt("hm:s:t:u");
  if (array_key_exists("h", $opts)) {
    die("php xreference.php -m <input markdown file> -s <section map file "
        . "[-t <text used for clickable reference link ] "
        . "[-u <update reference link text only; don't change actual link]"
        . PHP_EOL);
  }
  if (!array_key_exists("m", $opts) && !array_key_exists("s", $opts)) {
    die("Specify both input markdown file and input section map" . PHP_EOL);
  }
  $md_file = $opts["m"];
  $section_map_file = $opts["s"];
  $ref_link_text = null;
  if (array_key_exists("t", $opts)) {
    $ref_link_text = $opts["t"];
  }
  $update_only = false;
  if (array_key_exists("u", $opts)) {
    $update_only = true;
  }
  map_word_sections_to_markdown_sections($section_map_file, $md_file);
  $section_map = get_section_map($section_map_file);
  insert_cross_references($md_file, $section_map, $ref_link_text, $update_only);
}

main($argv);
