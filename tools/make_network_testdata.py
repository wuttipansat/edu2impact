"""Writes FAKE records into data/network.json and data/community.json of a COPY of the site, for tools/test_network_maps.py.

  python3 tools/make_network_testdata.py /path/to/copy-of-site

Never run it on the repo itself: the real files start as [] and test records must not be committed.
"""
import json, os, sys
root = sys.argv[1]
if os.path.exists(os.path.join(root, '.git')):
    sys.exit('refusing to write test records into a git checkout; copy the site first (tar --exclude=.git)')
ids = lambda k: [x['id'] for x in json.load(open(f'{root}/data/{k}.json'))]
r, i, w = ids('research'), ids('innovations'), ids('workshops')
rel = lambda *a: [{"kind": k, "id": v} for k, v in a]
net = [
    {"country_code": "JP", "institution": "TEST Kyoto University", "institution_th": "มหาวิทยาลัยเกียวโต (ทดสอบ)", "description": "ข้อมูลทดสอบ ไม่ใช่ข้อมูลจริง", "url": "https://example.org/", "related": rel(("research", r[0]), ("innovations", i[0]))},
    {"country_code": "JP", "institution": "TEST Tokyo Institute", "description": "ข้อมูลทดสอบ"},
    {"country_code": "SG", "institution": "TEST National Institute of Education", "description": "ข้อมูลทดสอบ (ประเทศเล็ก ต้องมีวงกลมให้กด)"},
    {"country_code": "US", "institution": "TEST University A"}, {"country_code": "US", "institution": "TEST University B"},
    {"country_code": "US", "institution": "TEST University C"}, {"country_code": "US", "institution": "TEST University D"},
    {"country_code": "DE", "institution": "TEST Uni DE"}, {"country_code": "VN", "institution": "TEST Uni VN"},
    {"country_code": "MY", "institution": "TEST Uni MY"}, {"country_code": "AU", "institution": "TEST Uni AU"},
    {"country_code": "BR", "institution": "TEST Uni BR"}, {"country_code": "KE", "institution": "TEST Uni KE"},
    {"country_code": "GB", "institution": "TEST Uni GB"}, {"country_code": "HK", "institution": "TEST Uni HK"},
    {"country_code": "ZZ", "institution": "unknown code, must be ignored"},
]
com = [
    {"province_code": "TH-50", "title": "TEST โครงการเชียงใหม่", "community": "ชุมชนทดสอบ", "summary": "ข้อมูลทดสอบ", "year": 2568, "related": rel(("innovations", i[0]), ("workshops", w[0]))},
    {"province_code": "TH-40", "title": "TEST โครงการขอนแก่น"},
    {"province_code": "TH-90", "title": "TEST สงขลา 1"}, {"province_code": "TH-90", "title": "TEST สงขลา 2"},
    {"province_code": "TH-83", "title": "TEST ภูเก็ต 1"}, {"province_code": "TH-83", "title": "TEST ภูเก็ต 2"},
    {"province_code": "TH-83", "title": "TEST ภูเก็ต 3"}, {"province_code": "TH-83", "title": "TEST ภูเก็ต 4"},
    {"province_code": "TH-10", "title": "TEST กรุงเทพ"}, {"province_code": "TH-12", "title": "TEST นนทบุรี"},
    {"province_code": "TH-38", "title": "TEST บึงกาฬ"},
]
json.dump(net, open(f'{root}/data/network.json', 'w'), ensure_ascii=False, indent=1)
json.dump(com, open(f'{root}/data/community.json', 'w'), ensure_ascii=False, indent=1)
print(len(net), len(com))
