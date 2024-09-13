const axios = require('axios');
const fs = require('fs');
const path = require('path');

const cacheFolder = path.join(__dirname, '_cache_');
const cacheTime = 691200; // 8 days in seconds
const url = 'https://babel-in.xyz/babel-b2ef9ad8f0d432962d47009b24dee465/tata/channels';

const fetchData = async () => {
  try {
    // Ensure cache folder exists
    if (!fs.existsSync(cacheFolder)) {
      fs.mkdirSync(cacheFolder, { recursive: true });
    }

    // Fetch data from the API
    const response = await axios.get(url, { headers: { 'User-Agent': 'Babel-In' } });
    const data = response.data;

    if (data && data.data) {
      await Promise.all(data.data.map(async (channel) => {
        const channelId = channel.id;
        const channelKey = channel.channel_key || null;

        if (channelKey) {
          const keys = channelKey.keys || [];
          const k = keys.length > 0 ? keys[0].k : null;
          const kid = keys.length > 0 ? keys[0].kid : null;

          const cacheFile = path.join(cacheFolder, `${channelId}.json`);
          let existingData = [];

          if (fs.existsSync(cacheFile)) {
            existingData = JSON.parse(fs.readFileSync(cacheFile));
            const keyExists = existingData.some(value => 
              value.keys.some(key => key.k === k && key.kid === kid)
            );

            if (!keyExists) {
              existingData = existingData.filter(value => {
                const timeAdded = new Date(value.time_added).getTime() / 1000;
                const currentTime = Math.floor(Date.now() / 1000);
                return (currentTime - timeAdded) <= cacheTime;
              });

              existingData.push({
                keys: [{ kty: 'oct', k: k, kid: kid }],
                type: 'temporary',
                time_added: new Date().toISOString()
              });

              fs.writeFileSync(cacheFile, JSON.stringify(existingData, null, 2));
            }
          } else {
            const newData = [{
              keys: [{ kty: 'oct', k: k, kid: kid }],
              type: 'temporary',
              time_added: new Date().toISOString()
            }];

            fs.writeFileSync(cacheFile, JSON.stringify(newData, null, 2));
          }
        } else {
          console.log(`Channel key is null for channel ID ${channelId}`);
        }
      }));
    } else {
      console.log('Failed to retrieve or decode data.');
    }
  } catch (error) {
    console.error('Error fetching or processing data:', error);
  }
};

fetchData();
