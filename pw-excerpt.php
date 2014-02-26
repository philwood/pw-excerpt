<?php
/*
Plugin Name: WP-Excerpt
Plugin URI: http://www.phillipwood.me/
Version: 1.0
Author: Phil Wood
Description: Enables control over the length of the excerpt while maintaining html tag structure. It records open tags and closes accordingly.
*/

//Add new filter to affect the Excerpt
remove_filter('get_the_excerpt', 'wp_trim_excerpt');
add_filter('get_the_excerpt', 'improved_trim_excerpt');

function improved_trim_excerpt($text) { // Fakes an excerpt if needed
	global $post;
	if ( '' == $text ) {
		$text = get_the_content('');
		$text = apply_filters('the_content', $text);
		$text = str_replace('\]\]\>', ']]&gt;', $text);

		$text = printTruncated(500,$text);

		$text  = preg_split("/\s+(?=\S*+$)/", $text); #Splits at last whitespace
		$beg = $text[0]; #Beginning of string
		if(isset($text[1])){
			$end = $text[1]; #End of string
			$end = strstr($end, "<"); #Strips everything before '<'
			$end = '&nbsp;&hellip;' . $end;
		}
		else{
			$end = '';
		}
		$text = $beg.$end;
	}
	return $text;
}

//Print Truncated function
function printTruncated($maxLength, $html, $isUtf8=true) {
    ob_start();
    $printedLength = 0;
    $position = 0;
    $tags = array();

    #For UTF-8, we need to count multibyte sequences as one character.
    $re = $isUtf8
        ? '{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;|[\x80-\xFF][\x80-\xBF]*}'
        : '{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;}';

    while ($printedLength < $maxLength && preg_match($re, $html, $match, PREG_OFFSET_CAPTURE, $position)){
        list($tag, $tagPosition) = $match[0];

        #Print text leading up to the tag.
        $str = substr($html, $position, $tagPosition - $position);
        if ($printedLength + strlen($str) > $maxLength){
            print(substr($str, 0, $maxLength - $printedLength));
            $printedLength = $maxLength;
            break;
        }

        print($str);
        $printedLength += strlen($str);
        if ($printedLength >= $maxLength) break;

        if ($tag[0] == '&' || ord($tag) >= 0x80){
            #Pass the entity or UTF-8 multibyte sequence through unchanged.
            print($tag);
            $printedLength++;
        }
        else{
            #Handle the tag.
            $tagName = $match[1][0];
            if ($tag[1] == '/'){
                #This is a closing tag.

                $openingTag = array_pop($tags);
                assert($openingTag == $tagName); #check that tags are properly nested.

                print($tag);
            }
            else if ($tag[strlen($tag) - 2] == '/'){
                #Self-closing tag.
                print($tag);
            }
            else{
                #Opening tag.
                print($tag);
                $tags[] = $tagName;
            }
        }

        #Continue after the tag.
        $position = $tagPosition + strlen($tag);
    }

    #Print any remaining text.
    if ($printedLength < $maxLength && $position < strlen($html))
        print(substr($html, $position, $maxLength - $printedLength));

    #Close any open tags.
    while (!empty($tags))
        printf('</%s>', array_pop($tags));
    
    return ob_get_clean();
}