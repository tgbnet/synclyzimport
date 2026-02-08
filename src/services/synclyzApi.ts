const API_BASE_URL = 'https://synclyz.com/api/v2';

export interface SynclyzUser {
  username: string;
  email?: string;
  account_type: 'Free' | 'Paid';
  level_id: string;
  storage_used?: string;
  storage_limit?: string;
}

export const synclyzAuth = async (username: string, apiKey: string) => {
  try {
    const response = await fetch(`${API_BASE_URL}/account/info`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({
        api_key: apiKey,
        username
      })
    });

    const data = await response.json();

    if (data.error) {
      throw new Error(data.error.message || 'Authentication failed');
    }

    return data.result as SynclyzUser;
  } catch (error) {
    console.error('Synclyz Auth Error:', error);
    throw error;
  }
};

export const mapAccountType = (levelId: string) =>
  levelId === '1' ? 'Free' : 'Paid';

export const fetchStorageSummary = async (apiKey: string) => {
  const response = await fetch(`${API_BASE_URL}/account/info`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
      api_key: apiKey
    })
  });

  if (!response.ok) {
    throw new Error('Unable to fetch storage summary');
  }

  const data = await response.json();
  return data.result as SynclyzUser;
};
