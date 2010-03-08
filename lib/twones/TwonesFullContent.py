__doc__ = '''Twones specific content saver plugin'''

import re

import feedworker
import feedworker.urn
import beanstalkc
import anyjson

class TwonesFullContentPlugin(feedworker.FullContent.FullContentPlugin):
    def _hasEnclosure(self, id):
        '''Checks if the given enclosure was sent to Twones.'''

        self.transaction.execute("""SELECT COUNT(enclosure_id) FROM twones_enclosure WHERE enclosure_id = %s""", (id,))
        x = self.transaction.fetchone()
        return (int(x[0]) > 0)

    def _saveEnclosure(self, transaction, collection, item, enclosure):
        '''Saves an enclosure to Twones.'''
        
        # call the method of the duper class 
        feedworker.FullContent.FullContentPlugin._saveEnclosure(self, transaction, collection, item, enclosure)

        # check for mp3 links
        if not re.search('\.mp3$', enclosure['link']):
            return
        
        # if the enclosure is saved successfully and if it has no enclosures already
        if enclosure.has_key("id") and not self._hasEnclosure(enclosure['id']):
            transaction.execute("""INSERT INTO twones_enclosure (enclosure_id, sent) VALUES(%s, NOW())""", (enclosure['id'], ))
            # print enclosure['link']
            if item.has_key('title'):
                item_title = item['title']
            else:
                item_title = None
            # Find URL associated with this item
            url = None 
            if item.has_key('links'):
                for relation in ['feedburner_origlink', 'alternate']:
                    if url is not None:
                        break
                    for link in item['links'].itervalues():
                        #print link
                        if link.has_key('relation') and link['relation'] == relation and link.has_key('link'):
                            url = link['link']
                            break
            # print url            
            # Find URL associated with this item
            service_url = None 
            if collection.has_key('links'):
                for link in collection['links'].itervalues():
                    if link.has_key('relation') and link['relation'] == 'alternate' and link.has_key('link'):
                        service_url = link['link']
                        break            
            json_obj = anyjson.serialize({
              'link': enclosure['link'],
              'web_link': url,
              'service_url': service_url,
              'post_title': item_title,
              'player': 'mp3'
            })
            self.beanstalk.put(json_obj)

    def pre_store(self):
        # print "Initiating beanstalk connection ..."
        self.beanstalk = beanstalkc.Connection()
        self.beanstalk.use('tracks')

    def post_store(self):
        # print "Destroying beanstalk connection ..."
        self.beanstalk.close()
        feedworker.FullContent.FullContentPlugin.post_store(self)
