export default async (req, res) => {
  try {
    const response = await fetch('https://api.github.com/graphql', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${process.env.GITHUB_TOKEN}`
      },
      body: JSON.stringify({
        query: `{ user(login:"seijicxz111"){contributionsCollection{contributionCalendar{totalContributions}}} }`
      })
    });

    const data = await response.json();
    res.status(200).json(data.data.user.contributionsCollection.contributionCalendar.totalContributions);
  } catch (error) {
    res.status(500).json({ error: 'Failed to fetch GitHub data' });
  }
};
