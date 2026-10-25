# Builds database/content-schema.sql from data/*.json: the content tables and every record in them.
# Import the result into e2imedu with phpMyAdmin (Import tab). It drops and rebuilds the content tables,
# so do not import it again after content has been edited in the database.
# Usage: python3 tools/json_to_sql.py [output.sql]
import json
import os,sys
ROOT=os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA=os.path.join(ROOT,'data')
OUT=sys.argv[1] if len(sys.argv)>1 else os.path.join(ROOT,'database','content-schema.sql')
def q(v):
    if v is None: return 'NULL'
    if isinstance(v,bool): return '1' if v else '0'
    if isinstance(v,(int,float)): return str(v)
    s=str(v).replace('\\','\\\\').replace("'","\\'").replace('\n','\\n').replace('\r','\\r').replace('\x00','')
    return "'"+s+"'"
def j(v): return None if v is None else json.dumps(v,ensure_ascii=False)
out=[]
out.append("""-- KUEDU2IMPACT: create tables and load the current content from data/*.json
-- Import this file into database e2imedu with phpMyAdmin (Import tab). Importing it again replaces everything in these four tables with the content of data/*.json.
SET NAMES utf8mb4;
SET time_zone = '+07:00';

DROP TABLE IF EXISTS post_links;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS site_settings;

CREATE TABLE posts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind        VARCHAR(20)  NOT NULL COMMENT 'research, innovations, workshops, media, news',
  slug        VARCHAR(120) NOT NULL COMMENT 'the old id; used in the URL ?id=',
  title       VARCHAR(400) NOT NULL,
  summary     TEXT NULL,
  body        LONGTEXT NULL COMMENT 'JSON array: paragraphs (text) and image blocks',
  refs        LONGTEXT NULL COMMENT 'JSON array of references, or NULL',
  image       VARCHAR(255) NULL,
  post_date   DATE NULL,
  author      VARCHAR(255) NULL,
  category    VARCHAR(100) NULL,
  tool_url    VARCHAR(500) NULL,
  tool_label  VARCHAR(150) NULL,
  featured    TINYINT(1) NOT NULL DEFAULT 0,
  is_public   TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0 COMMENT 'order in the old JSON file',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_kind_slug (kind, slug),
  KEY ix_kind_public (kind, is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_links (
  from_kind  VARCHAR(20)  NOT NULL,
  from_slug  VARCHAR(120) NOT NULL,
  to_kind    VARCHAR(20)  NOT NULL,
  to_slug    VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (from_kind, from_slug, to_kind, to_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='the old related field';

CREATE TABLE events (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(400) NOT NULL,
  meta       VARCHAR(400) NULL,
  type       VARCHAR(100) NULL,
  event_date DATE NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
  setting_key   VARCHAR(50) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
""")
known={'id','title','summary','body','references','image','date','author','category','tool_url','tool_label','featured','public','related'}
counts={}
for kind in ('research','innovations','workshops','media','news'):
    data=json.load(open(os.path.join(DATA,kind+'.json'),encoding='utf-8'))
    counts[kind]=len(data)
    for n,r in enumerate(data):
        extra=set(r)-known
        assert not extra,(kind,r['id'],extra)
        d=r.get('date') or None
        out.append("INSERT INTO posts (kind,slug,title,summary,body,refs,image,post_date,author,category,tool_url,tool_label,featured,is_public,sort_order) VALUES ("+
          ",".join([q(kind),q(r['id']),q(r['title']),q(r.get('summary')),q(j(r.get('body'))),q(j(r.get('references'))),q(r.get('image') or None),q(d),q(r.get('author') or None),q(r.get('category') or None),q(r.get('tool_url') or None),q(r.get('tool_label') or None),q(bool(r.get('featured'))),q(r.get('public',True) is not False),str(n)])+");")
        for m,l in enumerate(r.get('related') or []):
            out.append("INSERT INTO post_links (from_kind,from_slug,to_kind,to_slug,sort_order) VALUES ("+",".join([q(kind),q(r['id']),q(l['kind']),q(l['id']),str(m)])+");")
ev=json.load(open(os.path.join(DATA,'events.json'),encoding='utf-8'))
for n,e in enumerate(ev):
    out.append("INSERT INTO events (title,meta,type,event_date,sort_order) VALUES ("+",".join([q(e.get('title')),q(e.get('meta')),q(e.get('type')),q(e.get('date') or None),str(n)])+");")
site=json.load(open(os.path.join(DATA,'site.json'),encoding='utf-8'))
for k,v in site.items():
    out.append("INSERT INTO site_settings (setting_key,setting_value) VALUES ("+q(k)+","+q(json.dumps(v,ensure_ascii=False) if isinstance(v,bool) else v)+");")
out.append("""
-- Check after import (each row should match):
SELECT kind, COUNT(*) AS posts, SUM(is_public) AS public_posts FROM posts GROUP BY kind;""")
open(OUT,'w',encoding='utf-8').write("\n".join(out)+"\n")
print('wrote',OUT,counts, 'links', sum(1 for x in out if x.startswith('INSERT INTO post_links')), 'events',len(ev))
