# Load balancing reference code
The reference code in this repository demostrates possible implementation of load balancing techniques described in the following article: http://blog.wmspanel.com/2015/02/hls-dash-media-streaming-load-balancing.html

This code may be applied to perform balancing of any streaming like HLS, MPEG-DASH, SLDP, Icecast etc.

"geo-balancer" directory has a PHP class which allows performing balancing based on geo-location of a viewer. The test.php script shows class' proper usage with several examples.

"load-balancer" directory has set of PHP classes to perform load balancing based on current amount of bandwidth and connections at all available Nimble Streamer instances. The test.php script shows its proper usage.


This reference code is brought to you by WMSPanel team, https://wmspanel.com/
You can find Nimble Streamer real-time stats API reference here: https://wmspanel.com/nimble/api and Nimble Streamer control API here: https://wmspanel.com/api_info

Check our freeware streaming server called Nimble Streamer which is capable of HTTP-based media streaming using technologies like HLS, RTMP, MPEG-DASH, Icecast etc: https://wmspanel.com/nimble
