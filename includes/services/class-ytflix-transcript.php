<?php
if (!defined('ABSPATH')) exit;

use MrMySQL\YoutubeTranscript\TranscriptListFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

class YTFlix_Transcript {

    private function get_fetcher() {
        $http_client = new Client(['timeout' => 20]);
        $factory = new HttpFactory();
        return new TranscriptListFetcher($http_client, $factory, $factory);
    }

    public function get_transcript($video_post_id, $language = 'en') {
        global $wpdb;
        $table = $wpdb->prefix . 'ytflix_transcripts';

        $cached = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE video_post_id = %d AND language_code = %s",
            $video_post_id,
            $language
        ));

        if ($cached) {
            $fetched = strtotime($cached->fetched_at);
            $cache_duration = (int) get_option('ytflix_cache_duration', 3600);
            if ((time() - $fetched) < $cache_duration * 24) {
                return json_decode($cached->content, true);
            }
        }

        $youtube_id = get_post_meta($video_post_id, '_ytflix_youtube_id', true);
        if (empty($youtube_id)) return [];

        $transcript = $this->fetch_from_youtube($youtube_id, $language);

        if (!empty($transcript)) {
            $this->save_transcript($video_post_id, $youtube_id, $language, $transcript);
        }

        return $transcript;
    }

    private function fetch_from_youtube($youtube_id, $language = 'en') {
        try {
            $fetcher = $this->get_fetcher();
            $transcript_list = $fetcher->fetch($youtube_id);
            $transcript = $transcript_list->findTranscript([$language, 'en', 'bn']);
            $entries = $transcript->fetch();

            $captions = [];
            foreach ($entries as $entry) {
                $text = trim($entry['text'] ?? '');
                if (empty($text)) continue;

                $start = (float) ($entry['start'] ?? 0);
                $duration = (float) ($entry['duration'] ?? 0);

                $captions[] = [
                    'start'    => round($start, 2),
                    'end'      => round($start + $duration, 2),
                    'duration' => round($duration, 2),
                    'text'     => sanitize_text_field(html_entity_decode($text, ENT_QUOTES, 'UTF-8')),
                ];
            }

            return $captions;
        } catch (\Throwable $e) {
            error_log('YTFlix Transcript Error [' . $youtube_id . ']: ' . $e->getMessage());
            return [];
        }
    }

    private function save_transcript($video_post_id, $youtube_id, $language, $transcript) {
        global $wpdb;
        $table = $wpdb->prefix . 'ytflix_transcripts';

        $lang_names = [
            'en' => 'English', 'es' => 'Spanish', 'fr' => 'French',
            'de' => 'German', 'pt' => 'Portuguese', 'hi' => 'Hindi',
            'ja' => 'Japanese', 'ko' => 'Korean', 'zh' => 'Chinese',
            'ar' => 'Arabic', 'ru' => 'Russian', 'it' => 'Italian',
            'bn' => 'Bengali', 'ur' => 'Urdu', 'ta' => 'Tamil',
        ];

        $wpdb->replace($table, [
            'video_post_id' => $video_post_id,
            'youtube_id'    => $youtube_id,
            'language_code' => $language,
            'language_name' => $lang_names[$language] ?? ucfirst($language),
            'content'       => wp_json_encode($transcript),
            'fetched_at'    => current_time('mysql'),
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);
    }

    public function get_available_languages($video_post_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ytflix_transcripts';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT language_code, language_name FROM $table WHERE video_post_id = %d ORDER BY language_name",
            $video_post_id
        ));
    }

    public function export_transcript($video_post_id, $language = 'en', $format = 'txt') {
        $transcript = $this->get_transcript($video_post_id, $language);
        if (empty($transcript)) return '';

        if ($format === 'json') {
            return wp_json_encode($transcript, JSON_PRETTY_PRINT);
        }

        $output = '';
        foreach ($transcript as $entry) {
            $time = gmdate('H:i:s', (int)$entry['start']);
            $output .= "[{$time}] {$entry['text']}\n";
        }
        return $output;
    }
}
