<?php
if ( ! defined( 'ABSPATH' ) ) exit;
use Elementor\Controls_Manager;

/** Elementor widget: hero "This Week's Matches" ticker. */
class XHD_Live_Ticker_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'xhd-live-ticker'; }
	public function get_title() { return 'Live Matches Ticker'; }
	public function get_icon() { return 'eicon-countdown'; }
	public function get_categories() { return array( 'xhd', 'general' ); }
	public function get_keywords() { return array( 'football', 'live', 'matches', 'champions league', 'events' ); }
	public function get_script_depends() { return array( 'xhd-live' ); }
	public function has_widget_inner_wrapper(): bool { return false; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Matches' ) );
		$this->add_control( 'note', array( 'type' => Controls_Manager::RAW_HTML, 'raw' => 'Matches come from football-data.org and refresh automatically. Competition, API key, hidden/pinned matches and custom events: <b>WP Admin → Live Events</b>.', 'content_classes' => 'elementor-descriptor' ) );
		$this->add_control( 'title', array( 'label' => 'Title', 'type' => Controls_Manager::TEXT, 'default' => "This Week's Matches", 'label_block' => true ) );
		$this->add_control( 'max', array( 'label' => 'Max matches (0 = plugin setting)', 'type' => Controls_Manager::NUMBER, 'default' => 0, 'min' => 0, 'max' => 30 ) );
		$this->add_control( 'interval', array( 'label' => 'Autoplay (ms)', 'type' => Controls_Manager::NUMBER, 'default' => 4000, 'min' => 1500, 'step' => 500 ) );
		$this->end_controls_section();
	}
	protected function render() { $s = $this->get_settings_for_display(); echo XHD_Live::render_ticker( array( 'title' => $s['title'], 'max' => (int) $s['max'], 'interval' => (int) $s['interval'] ) ); }
}

/** Elementor widget: "Premium Events" match slider. */
class XHD_Live_Events_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'xhd-live-events'; }
	public function get_title() { return 'Live Matches Slider'; }
	public function get_icon() { return 'eicon-slider-video'; }
	public function get_categories() { return array( 'xhd', 'general' ); }
	public function get_keywords() { return array( 'football', 'live', 'matches', 'champions league', 'events', 'slider' ); }
	public function get_script_depends() { return array( 'xhd-live' ); }
	public function has_widget_inner_wrapper(): bool { return false; }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Matches' ) );
		$this->add_control( 'note', array( 'type' => Controls_Manager::RAW_HTML, 'raw' => 'Matches come from football-data.org and refresh automatically. Settings: <b>WP Admin → Live Events</b>.', 'content_classes' => 'elementor-descriptor' ) );
		$this->add_control( 'badge', array( 'label' => 'Badge', 'type' => Controls_Manager::TEXT, 'default' => 'Unlock Matches' ) );
		$this->add_control( 'overlay', array( 'label' => 'Bottom text', 'type' => Controls_Manager::TEXT, 'default' => 'Watch today’s matches by activating your subscription.', 'label_block' => true ) );
		$this->add_control( 'max', array( 'label' => 'Max matches (0 = plugin setting)', 'type' => Controls_Manager::NUMBER, 'default' => 0, 'min' => 0, 'max' => 30 ) );
		$this->add_control( 'interval', array( 'label' => 'Autoplay (ms)', 'type' => Controls_Manager::NUMBER, 'default' => 4000, 'min' => 1500, 'step' => 500 ) );
		$this->end_controls_section();
	}
	protected function render() { $s = $this->get_settings_for_display(); echo XHD_Live::render_events( array( 'badge' => $s['badge'], 'overlay' => $s['overlay'], 'max' => (int) $s['max'], 'interval' => (int) $s['interval'] ) ); }
}
