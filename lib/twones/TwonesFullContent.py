__doc__ = '''Twones specific content saver plugin'''

import feedworker
import feedworker.urn
import beanstalkc

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

        # if the enclosure is saved successfully and if it has no enclosures already
        if enclosure.has_key("id") and not self._hasEnclosure(enclosure['id']):
            transaction.execute("""INSERT INTO twones_enclosure (enclosure_id, sent) VALUES(%s, NOW())""", (enclosure['id'], ))
            # print enclosure['link']
            self.beanstalk.put(str(enclosure['link']))

    def pre_store(self):
        # print "Initiating beanstalk connection ..."
        self.beanstalk = beanstalkc.Connection()
        self.beanstalk.use('tracks')

    def post_store(self):
        # print "Destroying beanstalk connection ..."
        self.beanstalk.close()
        feedworker.FullContent.FullContentPlugin.post_store(self)
