using System.Collections.Generic;

namespace mvc.Models
{
    public class UUIViewModel
    {
        public string Task { get; set; }
        public string Host { get; set; }
        public string Token { get; set; }
        public string ReturnUrl { get; set; }
        public string State { get; set; }
        public int MaxTries { get; set; }
        public bool ChallengeResponse { get; set; }
        public string ChallengesJson { get; set; }
        public bool AutoEnroll { get; set; }
        public bool AutoStart { get; set; }
    }
}