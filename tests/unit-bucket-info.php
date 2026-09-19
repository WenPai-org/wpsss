<?php

define('WPSSS_TEST', true);
require dirname(__DIR__) . '/includes/class-wpsss-bucket-info.php';

$failed = 0;
function wpsss_assert(bool $ok, string $label): void
{
    global $failed;
    if ($ok) {
        echo "ok  $label\n";
        return;
    }
    $failed++;
    echo "FAIL  $label\n";
}

wpsss_assert(Wpsss_Bucket_Info::parse_location_xml('<LocationConstraint>oss-cn-hangzhou</LocationConstraint>') === 'oss-cn-hangzhou', 'oss location');
wpsss_assert(Wpsss_Bucket_Info::parse_location_xml('<LocationConstraint>ap-beijing</LocationConstraint>') === 'ap-beijing', 'cos location');
wpsss_assert(Wpsss_Bucket_Info::parse_location_xml('<LocationConstraint></LocationConstraint>') === '', 'empty location');
wpsss_assert(Wpsss_Bucket_Info::parse_acl_xml('<AccessControlList><Grant><Grantee><URI>http://acs.amazonaws.com/groups/global/AllUsers</URI></Grantee><Permission>READ</Permission></Grant></AccessControlList>') === 'public', 'public acl');
wpsss_assert(Wpsss_Bucket_Info::parse_acl_xml('<AccessControlPolicy><Owner/><AccessControlList/></AccessControlPolicy>') === 'signed', 'private acl');
wpsss_assert(Wpsss_Bucket_Info::app_id_from_cos_bucket('demo-1250000000') === '1250000000', 'cos appid');
wpsss_assert(Wpsss_Bucket_Info::app_id_from_cos_bucket('plain-bucket') === '', 'no appid');
wpsss_assert(Wpsss_Bucket_Info::region_from_endpoint('https://wenpai-wpsss-test.oss-cn-hangzhou.aliyuncs.com') === 'oss-cn-hangzhou', 'oss endpoint');
wpsss_assert(Wpsss_Bucket_Info::region_from_headers(['x-oss-bucket-region' => 'oss-cn-shanghai']) === 'oss-cn-shanghai', 'oss header');
wpsss_assert(Wpsss_Bucket_Info::pick_known_region('oss-cn-hangzhou', ['oss-cn-hangzhou' => '杭州']) === 'oss-cn-hangzhou', 'known region');
wpsss_assert(Wpsss_Bucket_Info::s3_empty_location_region() === 'us-east-1', 's3 empty');

if ($failed > 0) {
    fwrite(STDERR, $failed . " failed\n");
    exit(1);
}
echo "all passed\n";
