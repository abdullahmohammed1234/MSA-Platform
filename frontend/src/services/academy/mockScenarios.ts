import type { PracticeScenario } from "./scenariosTypes";

export const BRANDED_SCENARIOS: PracticeScenario[] = [
  {
    id: "booth-suffering",
    title: "Mercy and the Problem of Pain",
    difficulty: "Beginner",
    category: "Emotional Conversations",
    description: "Sarah approaches the MSA booth holding a brochure about 'God's Mercy'. She asks an emotionally heavy question about childhood illness and pain, testing your ability to bridge clinical logic with profound prophetic empathy.",
    characterName: "Sarah",
    characterRole: "SFU Biology Undergraduate",
    avatarSeed: "sarah",
    initialNodeId: "evil_intro",
    nodes: {
      evil_intro: {
        id: "evil_intro",
        characterText: "Hi. I'm looking at your pamphlet on 'God's Infinite Mercy,' but I have a hard time accepting it. If God is truly all-powerful and all-loving, why does He allow innocent children to suffer from terminal illnesses? It feels like either He isn't powerful, or He simply isn't merciful.",
        characterTone: "concerned",
        assistantInstruction: "Prioritize human connection first. Validate her genuine moral empathy for suffering children before trying to prove a theological standpoint.",
        options: [
          {
            id: "s1_opt_empathy",
            text: "Validate her compassion. Agree that seeing children suffer is deeply painful, and explain that in Islamic theology, this life is not an end but a temporary bridge of testing for growth and ultimate reward.",
            score: 100,
            nextNodeId: "evil_growth_life",
            outcomeSummary: "Sarah feels heard and respects your human empathy, making her open to discussing the purpose of this life.",
            emotionalFeedback: {
              characterToneImpact: "calm",
              atmosphereChange: "Sarah relaxes her posture slightly, seeing you don't instantly dismiss her pain."
            },
            mentorFeedback: {
              mentorName: "Shaykh Yusuf",
              scoreExplanation: "Perfect scores across empathy and character. Islam isn't just an abstract intellectual concept; you must speak to the heart before addressing the mind.",
              encouragement: "Wonderful job! You followed the prophetic method by comforting her moral instincts.",
              improvementSuggestions: [
                "Consider introducing the specific Arabic term 'Dar al-Ibtila' (Abode of Testing) once trust is fully established."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Excellent emotional mirroring and validation." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Properly framed Dunya as a temporary abode." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Avoided argumentative defensive trap." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "I completely understand why that breaks your heart, Sarah. Honestly, it breaks mine too. If we didn't feel pain at that, it would mean our hearts are dead. The Quran actually frames this world not as a paradise of final compensation, but as a temporary testing ground.",
              whyItWorks: "It establishes common moral ground. It shows that her anger at child suffering isn't a sign of disbelief, but a beautiful human instinct that actually aligns with Islamic compassion.",
              improvementTips: [
                "Never start with: 'The answer to your question is...'. Instead, say: 'I appreciate you bringing up such an important and heavy topic.'"
              ],
              recommendedLessons: [
                { title: "Compassionate Outreach & Posture", moduleUrl: "/courses/prophetic-dialogue-101", difficulty: "Beginner" },
                { title: "Understanding Qadar (Divine Decree)", moduleUrl: "/courses/theology-foundation", difficulty: "Intermediate" }
              ],
              relatedResources: [
                { name: "The Divine Wisdom in Suffering - Article", type: "article", url: "/resources/divine-wisdom" },
                { name: "Socratic Outreach on College Campuses", type: "video", url: "/resources/socratic-method" }
              ]
            }
          },
          {
            id: "s1_opt_theology",
            text: "Deliver a dense philosophical proof immediately. State that God's wisdom is infinite and humans are limited, meaning she has a keyhole view of a vast tapestry and lacks the knowledge to judge God.",
            score: 70,
            nextNodeId: "evil_philosophical_trap",
            outcomeSummary: "Sarah responds with frustration, feeling intellectually scolded rather than understood.",
            emotionalFeedback: {
              characterToneImpact: "defensive",
              atmosphereChange: "Sarah crosses her arms and narrows her eyes, preparing for a theological debate."
            },
            mentorFeedback: {
              mentorName: "Dr. Tariq",
              scoreExplanation: "Your philosophical facts are completely correct, but your delivery is too cold for an outreach setting.",
              encouragement: "You have strong academic roots, which is excellent. We just need to soften the container.",
              improvementSuggestions: [
                "Slow down. Do not rush to defend. Allow the student to fully express her grief first."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 4, maxScore: 10, feedback: "Neglected her feelings in favor of dry axioms." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Perfect explanation of God's all-encompassing Hikmah." },
                { rubric: "Dialogue Posture", score: 7, maxScore: 10, feedback: "Sounded slightly paternalistic or overly formal." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "You make a very heavy point. While we can unpack the logical reality that a finite mind cannot evaluate an infinite designer, let's first acknowledge how incredibly difficult it is to look at human pain.",
              whyItWorks: "Bridges the dry epistemology with human warmth, making the philosophy easier to digest.",
              improvementTips: [
                "Acknowledge before explaining.",
                "Avoid technical terminology like 'epistemological superiority' in the first 60 seconds."
              ],
              recommendedLessons: [
                { title: "Epistemology & Divine Attributes", moduleUrl: "/courses/theology-depth", difficulty: "Advanced" }
              ],
              relatedResources: [
                { name: "Reason & Revelation Overview", type: "guide", url: "/resources/reason-revelation" }
              ]
            }
          }
        ]
      },
      evil_growth_life: {
        id: "evil_growth_life",
        characterText: "Okay, I appreciate that you didn't just rattle off robotic textbook jargon. But if life is testing, why would a merciful God create such an extreme test? Sparing innocent children from excruciating illness seems like the bare minimum for a peaceful world.",
        characterTone: "thoughtful",
        assistantInstruction: "Introduce the Islamic concept of the Afterlife. Show how the ledger of earthly suffering is balanced with absolute justice and infinite compensation in Jannah.",
        options: [
          {
            id: "s2_opt_afterlife_justice",
            text: "Explain that Islamic theology rests on the eternity of existence. Earthly life is a microsecond compared to eternity, and every needle-prick of pain endured is compensated with absolute, unending bliss and elevated ranks in Jannah.",
            score: 100,
            nextNodeId: "evil_resolution_perfect",
            outcomeSummary: "Sarah is visibly moved by the idea of comprehensive cosmic justice where zero suffering is wasted.",
            emotionalFeedback: {
              characterToneImpact: "receptive",
              atmosphereChange: "Sarah shifts to a contemplative state, leaning in with visible interest."
            },
            mentorFeedback: {
              mentorName: "Shaykh Yusuf",
              scoreExplanation: "Outstanding. You successfully tied temporary suffering to the majestic expanse of Jannah. That is the core perspective that secular frameworks lack.",
              encouragement: "You have framed eternal justice beautifully. Continue integrating the absolute hope of the next life.",
              improvementSuggestions: [
                "Quote the Prophetic narration regarding the person who suffered the most in this world, who is dipped once in Jannah and forgets all pain."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Very gentle transition into faith-based metaphysics." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Masterfully explained Jannah as the ultimate equalizer." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Maintained a high-trust, respectful academic rhythm." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "Sarah, in the Islamic view, if this short life was the entire story, suffering would indeed be deeply tragic. But Islam promises that God's ledger never closes at death. There is an afterlife (Jannah) where anyone who suffered is rewarded with such immense joy.",
              whyItWorks: "Provides a logical necessity for an afterlife—if we want true justice, this world is mathematically insufficient to provide it.",
              improvementTips: [
                "Use analogies like comparing the womb of a mother to our earthly life. A baby in the womb doesn't understand why they must go through the struggle."
              ],
              recommendedLessons: [
                { title: "The Soul's Journey & Jannah", moduleUrl: "/courses/hereafter-studies", difficulty: "Beginner" }
              ],
              relatedResources: [
                { name: "Cosmic Justice & Resurrection - Video Lecture", type: "video", url: "/resources/cosmic-justice" }
              ]
            }
          }
        ]
      },
      evil_philosophical_trap: {
        id: "evil_philosophical_trap",
        characterText: "That sounds like an intellectual cop-out. Telling me I should just be blind and accept 'God's plan' because I'm small is exactly how people protect week theories. If He's loving, He could easily communicate He and ease He, and not hide behind mystery.",
        characterTone: "agitated",
        assistantInstruction: "Acknowledge that your initial response sounded defensive. Apologize sincerely, and bridge the conversation back to human vulnerability and Divine Wisdom.",
        options: [
          {
            id: "s2_opt_apology_recovery",
            text: "Apologize humbly. Say: 'You're absolutely right, Sarah. I answered too clinically, and I apologize. Let's step back. Of course God doesn't expect blind obedience. Let's look at why He created this world as a testing ground rather than a playground.'",
            score: 95,
            nextNodeId: "evil_growth_life",
            outcomeSummary: "Sarah is surprised and disarmed by your intellectual humility and agrees to hear the test analogy again with proper positioning.",
            emotionalFeedback: {
              characterToneImpact: "calm",
              atmosphereChange: "Sarah uncrosses her arms and nods, deeply respecting your lack of pride."
            },
            mentorFeedback: {
              mentorName: "Sister Yasmin",
              scoreExplanation: "Humble pie! This was a stunning recovery. Showing that Muslims are capable of recognizing when they sound too robotic builds immense goodwill.",
              encouragement: "I am extremely proud of your lack of arrogance. This is a crucial skill in public dawah.",
              improvementSuggestions: [
                "Now that trust is re-established, proceed with the Dunya-as-a-school analogy."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Superb humility and sincere self-correction." },
                { rubric: "Theological Accuracy", score: 8, maxScore: 10, feedback: "Apology clears the path for true theological dialogue." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Ultimate high mark in dialogical character." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "You are entirely right, Sarah, and I want to apologize. I sounded like a textbook just now. If we look at Islam, God actually commands us *not* to follow blindly.",
              whyItWorks: "It builds a bridge. Rather than asserting superiority, you admit fault and validate her demand for rational communication.",
              improvementTips: [
                "Sincere apologies are bulletproof outreach tools."
              ],
              recommendedLessons: [
                { title: "Prophetic Humility in Public Spheres", moduleUrl: "/courses/etiquette-excellence", difficulty: "Intermediate" }
              ],
              relatedResources: [
                { name: "The Art of Listening in Da'wah - Handbook", type: "guide", url: "/resources/listening-skills" }
              ]
            }
          }
        ]
      },
      evil_resolution_perfect: {
        id: "evil_resolution_perfect",
        characterText: "Wow. That... actually changes the entire paradigm. If this life is truly just a brief flash, and Jannah is the ultimate equalizer where justice is perfectly served, then pain isn't meaningless cruelty. Thank you for taking the time to share this perspective.",
        characterTone: "friendly",
        assistantInstruction: "End the scenario gracefully. Provide a warm invitation to learn more and exchange contact details or hand over a helpful introductory copy of the Quran translation.",
        options: [
          {
            id: "s3_opt_graceful_end",
            text: "Thank her for her incredible depth and sincerity. Hand her a high-quality copy of 'The Clear Quran' English translation and invite her to return to the weekly coffee booth anytime.",
            score: 100,
            nextNodeId: null,
            outcomeSummary: "Sarah accepts the Quran with a genuine smile and feels a sense of connection to the MSA community.",
            emotionalFeedback: {
              characterToneImpact: "receptive",
              atmosphereChange: "Sarah smiles broadly, shaking hands or nodding warmly, thoroughly satisfied."
            },
            mentorFeedback: {
              mentorName: "Shaykh Yusuf",
              scoreExplanation: "Mashallah, a pristine ending! This is how dawah is done. You left her with an open door and the Speech of Allah.",
              encouragement: "A beautiful display of hikmah (wisdom) and adab (manners). You represent the MSA with great distinction.",
              improvementSuggestions: [
                "Add her to our campus newsletter if she ever expresses interest in future comparative dialogue circles."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Warm, respectful, and perfectly aligned." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Presented the invitation logically." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Perfect outreach posture throughout." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "I am so grateful for this conversation, Sarah. People like you with deep empathy are exactly who the Quran speaks to. I'd love to gift you this copy.",
              whyItWorks: "Reinforces her positive feelings, frames her empathy as a Quranic asset, and keeps the door open.",
              improvementTips: [
                "Handing out literature is best when done as a gift of appreciation."
              ],
              recommendedLessons: [
                { title: "Follow-up & Building Trust Circles", moduleUrl: "/courses/dialogue-groups", difficulty: "Intermediate" }
              ],
              relatedResources: [
                { name: "Introductory Gift Selections for Seekers", type: "guide", url: "/resources/gift-guides" }
              ]
            }
          }
        ],
        reflectionPrompt: {
          question: "Sarah's journey shifted from intellectual distress to contemplative curiosity. What was the turning point in your dialogue style, and how can you apply this when encountering questions that feel like hostile attacks initially?",
          placeholder: "Write your reflection here. How does centering theological answers compare with first centering human empathy?",
          mentorSampleAnswer: "The key was validating her empathy rather than treating her query as an abstract logic board. When a skeptic senses you feel the weight of suffering too, they stop viewing you as an ideological adversary and start viewing you as a partner in seeking the truth."
        }
      }
    }
  },
  {
    id: "scripture-reliability",
    title: "Skepticism & Scripture Translation",
    difficulty: "Intermediate",
    category: "Difficult Questions",
    description: "Professor Liam, a secular philosophy sessional lecturer, halts by the MSA table. He challenges the historical preservation of the Quranic text, claiming all 1400-year-old scriptures are heavily mutated copyist translations.",
    characterName: "Professor Liam",
    characterRole: "Sessional Philosophy Lecturer",
    avatarSeed: "liam",
    initialNodeId: "scrip_intro",
    nodes: {
      scrip_intro: {
        id: "scrip_intro",
        characterText: "Pardon me. I see you are sharing the Quran here. As a philosopher, I find it hard to understand how someone in the 21st century can base their morality on a manual written 1400 years ago. Like the Bible, it must have been translated, copied, and edited so many times that the original manuscript is practically lost. How do you deal with that?",
        characterTone: "skeptical",
        assistantInstruction: "Deconstruct the assumption of Gutenberg-style visual-copyist preservation. Explain the oral memorization methodology of the Quran (Hifz) and direct verbal transmission chains.",
        options: [
          {
            id: "sc_opt_oral_system",
            text: "Explain that the Quran's primary preservation has always been oral, not textual. Show that millions memorize it letter-for-letter in original Arabic, preventing scribal alteration, backed by a rigorous chain of oral transmission (Isnad).",
            score: 100,
            nextNodeId: "scrip_oral_objection",
            outcomeSummary: "Professor Liam is intellectually intrigued by the concept of oral transmission, recognizing it bypassed standard copying defects.",
            emotionalFeedback: {
              characterToneImpact: "thoughtful",
              atmosphereChange: "Liam strokes his chin, moving into academic evaluation mode."
            },
            mentorFeedback: {
              mentorName: "Dr. Tariq",
              scoreExplanation: "Outstanding analytical work. You identified and corrected his false premise that the Quran was preserved like medieval European manuscripts.",
              encouragement: "Excellent preservation of scholastic terminology without sounding pedantic.",
              improvementSuggestions: [
                "Draw attention to the phonetic sciences of Quranic recitation (Tajweed) as an additional absolute preservation layer."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 8, maxScore: 10, feedback: "Respectful and academic framing of the answer." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Perfect explanation of Hifz and oral compilation history." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Maintained pristine post-graduate dialogical standard." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "You make an exceptional point, Professor Liam, regarding the vulnerability of copyist scripts. However, Islamic scriptural preservation operates on a completely different paradigm. The Quran was primarily preserved orally. Millions of people memorize it word-for-word in its original Arabic, reciting it synchronously in daily prayers. This oral codification has historically bypassed the scribal errors that plagued standard written texts.",
              whyItWorks: "Validates his expertise on copyist defects first, then introduces the unique oral framework of Islamic compilation.",
              improvementTips: [
                "Avoid telling him his philosophy is wrong; instead, introduce the oral paradigm as a historical uniqueness."
              ],
              recommendedLessons: [
                { title: "Quran Preservation & Compilation Sciences", moduleUrl: "/courses/uloom-al-quran", difficulty: "Intermediate" }
              ],
              relatedResources: [
                { name: "Orality and Textuality in Early Islam - Essay", type: "article", url: "/resources/orality-islam" }
              ]
            }
          }
        ]
      },
      scrip_oral_objection: {
        id: "scrip_oral_objection",
        characterText: "Oral transmission sounds fascinating, but as a scientist, I must raise an objection. Human memory is notoriously plastic and unreliable. If you pass a phrase through a chain of even five individuals orally, the message gets distorted. This is basic psychology. How can you expect me to trust a 1400-year chain of human whispers?",
        characterTone: "curious",
        assistantInstruction: "Deconstruct the 'Chinese Whispers' analogy. Explain the scientific, public, and concurrent nature of 'Mutawatir' (concurrence of multiple independent lines of transmission).",
        options: [
          {
            id: "sc_opt_mutawatir_math",
            text: "Contrast whispers with the concept of 'Tawatur.' Explain that checking a text concurrently through thousands of independent memorizers across huge geographic regions rules out systematic error or collusion. Memory errors are isolated and instantly corrected by the wider community during public recitations.",
            score: 100,
            nextNodeId: "scrip_perfect_finish",
            outcomeSummary: "Liam acknowledges the mathematical strength of mass parallel transmission checking versus serial whispers chains.",
            emotionalFeedback: {
              characterToneImpact: "receptive",
              atmosphereChange: "Liam nods in appreciation of the mathematical-epistemic rigor you presented."
            },
            mentorFeedback: {
              mentorName: "Dr. Tariq",
              scoreExplanation: "Incredible validation of Islamic epistemological methodology. Explaining Tawatur as a decentralized parallel processing ledger is both modern and highly accurate.",
              encouragement: "Brilliantly handled. You showed him that Islamic scriptural preservation utilizes an error-correcting networking process.",
              improvementSuggestions: [
                "You could make an analogy with modern blockchain ledger systems or peer-to-peer verification schemas."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 9, maxScore: 10, feedback: "Very respectful of his scientific critique." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Perfect framing of Tawatur and Hadith/Quran transmission sciences." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Scholarly, precise, and highly persuasive." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "That is an excellent critique, Professor. The 'telephone game' analogy holds true for serial private whispers. But Islamic transmission uses a decentralized, concurrent verification network (Tawatur). It is as if thousands of independent nodes across different continents are reciting the exact same text concurrently daily.",
              whyItWorks: "Uses modern peer-to-peer and decentralized ledger models, which explain high-level traditional concept in a language contemporary intellectuals instantly admire.",
              improvementTips: [
                "Use the term 'communal error-correction network' to describe congregational prayers."
              ],
              recommendedLessons: [
                { title: "Advanced Hadith & Transmission Epistemology", moduleUrl: "/courses/hadith-epistemology", difficulty: "Advanced" }
              ],
              relatedResources: [
                { name: "Epistemology of Testimony and Tawatur - Paper", type: "article", url: "/resources/testimonies-tawatur" }
              ]
            }
          }
        ]
      },
      scrip_perfect_finish: {
        id: "scrip_perfect_finish",
        characterText: "That is genuinely one of the most intellectually compelling historical frameworks I've heard. To conceptualize oral transmission not as a linear whisper game, but as mass-concurrent peer-to-peer consensus... that is epistemologically sound. Thank you for this engaging dialogue.",
        characterTone: "friendly",
        assistantInstruction: "End the academic talk gracefully. Gift him a critical translation with scholarly footnotes to encourage further investigation.",
        options: [
          {
            id: "sc_opt_academic_closing",
            text: "Provide a warm academic closing. Hand him the scholarly study Quran with extensive footnotes, and invite him to the quarterly campus interfaith research circle.",
            score: 100,
            nextNodeId: null,
            outcomeSummary: "Liam accepts the academic Quran with deep interest and respects the intellectual depth of the MSA.",
            emotionalFeedback: {
              characterToneImpact: "receptive",
              atmosphereChange: "Liam smiles under his beard, shaking hands with professional cordiality."
            },
            mentorFeedback: {
              mentorName: "Dr. Tariq",
              scoreExplanation: "Flawless academic outreach! You represented the scholarly heritage of Islam with absolute poise, satisfying a trained philosopher's skepticism completely.",
              encouragement: "Wonderful use of academic parameters. You have established serious cognitive credibility for the MSA booth.",
              improvementSuggestions: [
                "Provide a summary sheet of early Quranic manuscript carbon-dating reviews."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Highly professional and customized layout." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Pristine historical and linguistic methodology." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Perfect peer-level academic dialogue." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "Thank you, Professor Liam. It was a true pleasure. Let's stay in touch; we often host scholars to review classical Islamic epistemology.",
              whyItWorks: "Invites continuing discussion without applying conversion pressure, establishing long-term trust.",
              improvementTips: [],
              recommendedLessons: [],
              relatedResources: []
            }
          }
        ],
        reflectionPrompt: {
          question: "How did your approach change when presenting to an academic skeptic (Liam)? Why is matching your language register to your audience a vital aspect of prophetic wisdom?",
          placeholder: "Compare intellectual verification with emotional validation.",
          mentorSampleAnswer: "An academic skeptic needs epistemological rigor and objective standards first; vague emotional reassurances feel like circular evasions. Hikmah is addressing each person with what satisfies their particular standard of logic."
        }
      }
    }
  },
  {
    id: "socratic-101",
    title: "Socratic Outreach: Purpose of Existence",
    difficulty: "Beginner",
    category: "Beginner Conversations",
    description: "Evelyn walks past the booth looking curious but hesitant. She notes that religion seems like an outdated social construct. Practice engaging her using constructive, gentle socratic questioning to explore the purpose of life.",
    characterName: "Evelyn",
    characterRole: "SFU Psychology Student",
    avatarSeed: "evelyn",
    initialNodeId: "socratic_intro",
    nodes: {
      socratic_intro: {
        id: "socratic_intro",
        characterText: "Hi, I see you guys are representing the Muslim Students Association. Honestly, from a psychological perspective, religion just looks like security blanket humans invented to feel comfortable in a cold, meaningless universe. Why do we need a complex religious system to live a good life?",
        characterTone: "skeptical",
        assistantInstruction: "Do not defend with defensive arguments. Instead, ask a gentle question about how we define truth and meaning in our lives.",
        options: [
          {
            id: "socratic_opt_question",
            text: "Kindly validate her perspective: 'That's a very common psychological observation! If we look at things we create, like a watch or a smartphone, they always have a specified purpose. Do you think our human consciousness is the only thing that occurred by pure accident without any purpose?'",
            score: 100,
            nextNodeId: "socratic_reflection",
            outcomeSummary: "Evelyn receives the analogy warmly and considers the question deeply.",
            emotionalFeedback: {
              characterToneImpact: "thoughtful",
              atmosphereChange: "Evelyn leans against the table, tilting her head as she reflects."
            },
            mentorFeedback: {
              mentorName: "Sister Yasmin",
              scoreExplanation: "Perfect Socratic execution. Asking questions is a classic prophetic method that lets the speaker reach the conclusion herself.",
              encouragement: "Very warm and disarming! You avoided a defensive argument trap.",
              improvementSuggestions: [
                "Perfect transition: draw attention to human introspection versus automated animal survival."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Validates her background in psychology elegantly." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Directs focus to teleology / Fitra." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Excellent open-hand dialogue posture." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "I appreciate that psychology angle, Evelyn! It's true that we seek security. But if you think about it, why do we have a hunger for meaning in the first place? An animal is content with food and shelter, but humans have an insatiable appetite for ultimate justice and purpose. Where does that hunger come from in a meaningless universe?",
              whyItWorks: "Bridges her psychological language to teleology (the argument from desire).",
              improvementTips: ["Maintain an inquisitive, friendly tone."],
              recommendedLessons: [
                { title: "Socratic Method in Outreach", moduleUrl: "/courses/socratic-dialogue", difficulty: "Beginner" }
              ],
              relatedResources: []
            }
          }
        ]
      },
      socratic_reflection: {
        id: "socratic_reflection",
        characterText: "Mmh, that is a fair query. We definitely are unique in seeking purpose. But if there's a custom purpose, why is it coded inside a specific theology instead of just being intuitive?",
        characterTone: "curious",
        assistantInstruction: "Explain the Fitrah (intuitive human nature) and how messengers merely raw-calibrate what is already inscribed within the soul.",
        options: [
          {
            id: "socratic_opt_fitrah",
            text: "Explain that Islam actually recognizes this intuition as fitrah—an inner compass for truth that already exists, while revelation acts as a light that makes everything clear.",
            score: 100,
            nextNodeId: null,
            outcomeSummary: "Evelyn nods thoughtfully and agrees that the concept of an inner compass aligns perfectly with holistic psychology.",
            emotionalFeedback: {
              characterToneImpact: "receptive",
              atmosphereChange: "Evelyn smiles, her shoulders relaxing complete."
            },
            mentorFeedback: {
              mentorName: "Shaykh Yusuf",
              scoreExplanation: "Splendid! Fitrah is the foundation of outreach of comparative dialog. You connected psychology directly to the primordial state.",
              encouragement: "Very eloquent and sincere.",
              improvementSuggestions: [
                "Guide her to classical articles on how children natively believe in a transcendent builder."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Great validation of human moral intuition." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Excellently defined the concept of Fitrah." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Pristine, constructive dialogue." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "In Islam, we believe the core truth is already intuition within our soul. We call it 'Fitrah'. Messengers are not bringing an alien system; they are just clearing the dust off a mirror.",
              whyItWorks: "Validates her intuitive self-worth.",
              improvementTips: [],
              recommendedLessons: [],
              relatedResources: []
            }
          }
        ],
        reflectionPrompt: {
          question: "How does the Socratic method of using questions differ from delivering a sermon? Why is it more effective for beginners?",
          placeholder: "Reflect on student engagement versus academic lectures.",
          mentorSampleAnswer: "Questions allow the student to actively engage their intellect and discover truths themselves. This builds active neural buy-in and avoids triggering a defensive ego response."
        }
      }
    }
  },
  {
    id: "hadith-history",
    title: "Hadith Authenticity & Oral Records",
    difficulty: "Intermediate",
    category: "Misconceptions",
    description: "Auden approaches the booth with skepticism regarding the Hadith literature, asserting that oral records passed down over generations cannot possibly be relied upon for religious law. Practice explaining Hadith sciences.",
    characterName: "Auden",
    characterRole: "SFU History Student",
    avatarSeed: "auden",
    initialNodeId: "hadith_intro",
    nodes: {
      hadith_intro: {
        id: "hadith_intro",
        characterText: "Hi. I'm a history major and I'm somewhat skeptical of secondary sources. In Islam, you base so many laws on Hadiths, which were compiled hundreds of years after Muhammad passed away. Isn't that basically the telephone game? Scribes could easily alter testimonies to serve their rulers.",
        characterTone: "skeptical",
        assistantInstruction: "Acknowledge his historical background, and introduce the rigorous biographical evaluation sciences (Mustalah al-Hadith) and Isnad verification.",
        options: [
          {
            id: "hadith_opt_isnad",
            text: "Acknowledge his training: 'As a historian, you know source criticism is vital! But Hadith compilation is different: every narration requires a complete chain of eyewitnesses (Isnad), where every single narrator's character, life, memory, and political links were rigorously audited under biographical sciences.'",
            score: 100,
            nextNodeId: "hadith_evaluation",
            outcomeSummary: "Auden is surprised by the academic rigor of Hadith science and agrees it is a strong preservation framework.",
            emotionalFeedback: {
              characterToneImpact: "thoughtful",
              atmosphereChange: "Auden leans in, recognizing the historiographical terms."
            },
            mentorFeedback: {
              mentorName: "Dr. Tariq",
              scoreExplanation: "Perfect alignment. Validating his historian mindset made him appreciate the systematic rigor of Hadith sciences (Ilm ar-Rijal).",
              encouragement: "Superb historiographical bridge!",
              improvementSuggestions: [
                "Clarify that 'Mustalah' is actually more strict than standard modern peer-evaluation processes."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Mirrored his scholarly stance beautifully." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Great explanation of Isnad and biographical audits." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Brilliant, respectful academic engagement." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "You make a crucial historiographical point, Auden. Hadith critics actually invented biographical science (Asma ar-Rijal) to audit memory. Scribes couldn't just invent reports; they had to prove who they heard it from, and historians cross-checked independent reports.",
              whyItWorks: "Treats him as an equal-stature historical peer.",
              improvementTips: [],
              recommendedLessons: [
                { title: "Introduction to Hadith Sciences", moduleUrl: "/courses/hadith-101", difficulty: "Intermediate" }
              ],
              relatedResources: []
            }
          }
        ]
      },
      hadith_evaluation: {
        id: "hadith_evaluation",
        characterText: "Interesting! So it wasn't just a collective memory pool, but a structured witness registry. How did they handle narrators who were known to forget occasionally?",
        characterTone: "curious",
        assistantInstruction: "Explain the classification metric of Hadith based on narrator precision (Dabt) and grade classifications (Sahih vs Da'if).",
        options: [
          {
            id: "hadith_opt_dabt",
            text: "Explain that narrators with slight memory lapses had their reports graded as Hasan (good) or Da'if (weak). Only reports with absolute, flawless memory verification (Sahih) are used for foundational creeds.",
            score: 100,
            nextNodeId: null,
            outcomeSummary: "Auden appreciates the transparent and scientific grading system used by traditional scholars.",
            emotionalFeedback: {
              characterToneImpact: "receptive",
              atmosphereChange: "Auden smiles, taking a flyer about classical Islamic historiography."
            },
            mentorFeedback: {
              mentorName: "Dr. Tariq",
              scoreExplanation: "Outstanding. You successfully decoupled our theology from popular myths about telephone games and grounded it in peer-reviewed precision.",
              encouragement: "Masterclass in Hadith explanation!",
              improvementSuggestions: [
                "Remind him that Hadith grading contains several incremental steps, leaving zero room for speculation."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Kept a highly respectful, clean academic register." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Perfect explanation of Dabt and Sahih grading." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Excellent dialogue standard." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "Scholars did not tolerate memory lapses. If a narrator was known to make occasional errors, their Hadith is classified accordingly. Classical Islam had a highly rigorous peer review standard.",
              whyItWorks: "Bridges traditional terms with modern academic peer review ideas.",
              improvementTips: [],
              recommendedLessons: [],
              relatedResources: []
            }
          }
        ],
        reflectionPrompt: {
          question: "Why is it important to explain the classical audit science of Hadith rather than simply saying: 'These are holy and reliable'?",
          placeholder: "Reflect on how scientific classifications build credibility.",
          mentorSampleAnswer: "Stating oral records are 'holy' is circular to an outsider. Demonstrating the historical rigor, data audits, and witness checks used by early scholars establishes universal academic credibility."
        }
      }
    }
  },
  {
    id: "trinity-tawhid",
    title: "The Trinity & Absolute Tawhid",
    difficulty: "Advanced",
    category: "Interfaith Dialogue",
    description: "Father Thomas, a local chaplain, visits the booth to discuss early Christian-Islamic history and comparative Christology, exploring the absolute Divine Unity (Tawhid) versus Trinitarian creeds.",
    characterName: "Father Thomas",
    characterRole: "Anglican Campus Chaplain",
    avatarSeed: "thomas",
    initialNodeId: "trinity_intro",
    nodes: {
      trinity_intro: {
        id: "trinity_intro",
        characterText: "Hello! Peace be with you. I appreciate your active presence on campus. As a pastor, I love comparative theology. In Islam, you emphasize Tawhid (the absolute singularity of God) and reject the Trinity. Many Christians view the Trinity not as polytheism, but as three expressions of one divine essence. Why does Islamic theology take such a hard, uncompromising line against this conception of Jesus?",
        characterTone: "skeptical",
        assistantInstruction: "Acknowledge the shared spiritual focus and show how Islam returns Christology to the original Hebrew Prophetic monotheism, honoring Jesus as a Messiah without divinization.",
        options: [
          {
            id: "trinity_opt_monotheism",
            text: "Respond with ultimate reverence: 'We hold Jesus in absolute high regard as the Messiah! But in Islam, the core of all prophetic messages—from Abraham, Moses, to Jesus—is Tawhid. Splitting the Creator's absolute essence into persons, even with good intentions, compromises the absolute Transcendence of God that Jesus himself preached.'",
            score: 100,
            nextNodeId: "trinity_messiah",
            outcomeSummary: "Father Thomas appreciates the respectful framing and notes that Jesus' prophetic identity is indeed a historical debate in early councils.",
            emotionalFeedback: {
              characterToneImpact: "thoughtful",
              atmosphereChange: "Father Thomas nods slowly, maintaining a highly warm and pastoral posture."
            },
            mentorFeedback: {
              mentorName: "Shaykh Yusuf",
              scoreExplanation: "Superb. Reclaiming Jesus (Isa al-Masih) as our Prophet first before discussing monotheism is the perfect outreach posture for interfaith dialogues.",
              encouragement: "Highly warm, polite, and uncompromisingly clear on Tawhid.",
              improvementSuggestions: [
                "Perfect setup; keep emphasizing that monotheism was the original covenant of Abraham."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Respectful, polite, and highly cooperative." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Solid, clear presentation of absolute Monotheism." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Splendid chaplain-level interfaith posture." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "Welcome pastoral friend! We love speaking about Jesus, peace be upon him. In Islam, we regard him as one of the greatest messengers of God. But we believe that the purest form of monotheism is to keep the Creator entirely distinct from His creation, which protects Jesus' own original message.",
              whyItWorks: "Focuses on shared veneration and outlines the theological distinction peacefully.",
              improvementTips: [],
              recommendedLessons: [
                { title: "Comparative Christology & Islam", moduleUrl: "/courses/christology", difficulty: "Advanced" }
              ],
              relatedResources: []
            }
          }
        ]
      },
      trinity_messiah: {
        id: "trinity_messiah",
        characterText: "That is a beautiful way of putting it. It is true we both trace our faiths to Abraham. But if we view Jesus only as a Prophet, we miss the radical claim of God coming down to experience our human weakness intimately. Doesn't infinite love imply a God who suffers with us?",
        characterTone: "thoughtful",
        assistantInstruction: "Deconstruct the idea that divine love requires physical suffering. Explain that God's All-Sufficiency (Samad) and mercy do not require Him to compromise His absolute majesty.",
        options: [
          {
            id: "trinity_opt_samad",
            text: "Explain that in Islam, Allah is Al-Ghani (Self-Sufficient). His love and empathy for us are absolute and direct, not dependent on Him suffering. He created suffering as a trial for us, but He Himself transcends all mortal weakness, which is why we must direct all worship solely to His sublime majesty.",
            score: 100,
            nextNodeId: null,
            outcomeSummary: "Father Thomas respects the theological consistency of Islamic Transcendence.",
            emotionalFeedback: {
              characterToneImpact: "receptive",
              atmosphereChange: "The dialogue concludes on an exceptionally high-trust, respectful note."
            },
            mentorFeedback: {
              mentorName: "Shaykh Yusuf",
              scoreExplanation: "Outstanding work. You successfully highlighted the profound beauty of Al-Ghani and Al-Khaliq without alienating your pastor friend. Truly exemplary manners.",
              encouragement: "May Allah bless your speech. You have conducted a beautiful interfaith dialogue.",
              improvementSuggestions: [
                "Excellent job! Always close with a warm embrace of fellowship."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Extremely warm and courteous dialogue." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Flawless formulation of Tawhid al-Asma was-Sifat." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Perfect interfaith standard." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "We believe God's love does not require Him to descend into human weakness. His majesty is that He hears our struggles and sustains the universe without ever being touched by fatigue or pain Himself. This is the sublime beauty of Al-Khaliq.",
              whyItWorks: "Sublime presentation of Divine Majesty.",
              improvementTips: [],
              recommendedLessons: [],
              relatedResources: []
            }
          }
        ],
        reflectionPrompt: {
          question: "Why is it critical to establish mutual respect for Jesus before delving into the theological differences of Trinity vs Tawhid?",
          placeholder: "Reflect on Christology as a bridge rather than a wall.",
          mentorSampleAnswer: "Showing that Muslims also love, honor, and look to Jesus as a central Messenger breaks down the common secular/Christian prejudice that Muslims reject or dislike Christ, building immediate trust for theological discussion."
        }
      }
    }
  },
  {
    id: "booth-etiquette",
    title: "Adab Under Fire: De-escalating Anger",
    difficulty: "Beginner",
    category: "Booth Etiquette",
    description: "Marcus, a highly hostile student, starts shouting at the booth about geopolitics and religion to provoke you. Your task is to maintain absolute prophetic poise, control your anger, and de-escalate him safely.",
    characterName: "Marcus",
    characterRole: "Hostile Campus Provocateur",
    avatarSeed: "marcus",
    initialNodeId: "etiquette_intro",
    nodes: {
      etiquette_intro: {
        id: "etiquette_intro",
        characterText: "Oh great, the religious fanatics are here again! Honestly, why do you guys push your medieval views on our open university campus? Religions represent the root cause of all wars, suffering, and backwardness throughout human history! Tell me why we shouldn't just ban this display completely!",
        characterTone: "hostile",
        assistantInstruction: "Maintain absolute calm (Sabr). Do not match his high volume or address his provocation defensively. Speak with a low, calm, warm volume.",
        options: [
          {
            id: "etiquette_opt_calm",
            text: "Lower your voice volume, speak with extreme gentleness, hand over some bottled water: 'I can see you feel deeply passionate about this, Marcus. We are just here to share water and talk peacefully, not to force anything. Please take a seat or have a water bottle. We'd love to hear your thoughts comfortably.'",
            score: 100,
            nextNodeId: "etiquette_reflection_calm",
            outcomeSummary: "Marcus is visibly shocked by your absolute lack of defensiveness or anger. He stops shouting, though still tense.",
            emotionalFeedback: {
              characterToneImpact: "calm",
              atmosphereChange: "The high tension at the booth drops instantly. Onlookers note your beautiful poise."
            },
            mentorFeedback: {
              mentorName: "Sister Yasmin",
              scoreExplanation: "Perfect! Sabr (patience) is the ultimate weapon of the prophet. Returning heat with cool water is exactly as Allah commands in the Quran (Repel with that which is better).",
              encouragement: "Magnificent display of public prophetic character!",
              improvementSuggestions: [
                "Perfect! Always maintain a quiet physical presence and low voice."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Offered immediate hospitality and human warmth." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Perfect implementation of Quranic de-escalation guidelines." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Flawless, calm-hand dialogue posture." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "Peace, Marcus. I completely understand why seeing wars in the name of religion breaks your heart. Honestly, it breaks mine too. Many of us are refugees of those exact wars. We are just volunteers here trying to hand out standard literature. Settle down with us and have some water.",
              whyItWorks: "Dismantles his 'us vs them' projection immediately, exposing his targets as victims of suffering rather than facilitators of it.",
              improvementTips: ["Always keep a low, grounding vocal tone."],
              recommendedLessons: [
                { title: "Prophetic De-escalation & Calmness", moduleUrl: "/courses/prophetic-poise", difficulty: "Beginner" }
              ],
              relatedResources: []
            }
          }
        ]
      },
      etiquette_reflection_calm: {
        id: "etiquette_reflection_calm",
        characterText: "Uh... well, okay. I don't need water. But you still haven't answered my point. Religions literally cause backward thinking! Look at what some fanatics do in the name of God!",
        characterTone: "agitated",
        assistantInstruction: "Politely decouple the pristine divine message of Islam from the mistakes or abuses committed by fallible human agents.",
        options: [
          {
            id: "etiquette_opt_decouple",
            text: "Explain gently: 'You make a very real point, Marcus. People can abuse anything—science, politics, medicine, and religion—to justify bad actions. But we shouldn't judge a pristine textbook by the student who fails it. If we look at the life of the Prophet, he was sent as a mercy to all creation, teaching us to respect and care for every human soul.'",
            score: 100,
            nextNodeId: null,
            outcomeSummary: "Marcus relaxes, admitting that decoupling the philosophy from the human mistakes is a valid intellectual stance.",
            emotionalFeedback: {
              characterToneImpact: "thoughtful",
              atmosphereChange: "Marcus uncrosses his arms and nods slightly before moving on peacefully."
            },
            mentorFeedback: {
              mentorName: "Shaykh Yusuf",
              scoreExplanation: "Pristine. You de-escalated a dangerous campus conflict and earned the respect of everyone present. This is the true success of active dawah.",
              encouragement: "Incredible adab. You represents our academy with absolute excellence.",
              improvementSuggestions: [
                "Perfect end! Offer him an introductory book regarding actual Islamic rules of civilization."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Perfect tone control throughout." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Acknowledge fallibility versus divine texts correctly." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Magnificent poise under pressure." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "You make a crucial distinction, Marcus. If a surgeon misuses a scalpel and causes harm, we don't ban surgery; we blame the surgeon. Similarly, classical Islam has rules of war and ethics that prohibit harming even a single tree or civilian.",
              whyItWorks: "Uses a clean medical analogy that is instantly understandable.",
              improvementTips: [],
              recommendedLessons: [],
              relatedResources: []
            }
          }
        ],
        reflectionPrompt: {
          question: "When a person shouts or insults your faith publicly, why is reacting with anger an intellectual and spiritual failure for an outreach volunteer?",
          placeholder: "Reflect on the difference between defending your ego and representing truth.",
          mentorSampleAnswer: "Our ego wants to fight back and defend itself. But our role is to represent the mercy of Allah and the character of the Prophet (SAW). Matching hostility with anger turns the booth into a battlefield, confirming the skeptic's prejudices."
        }
      }
    }
  },
  {
    id: "comparative-theology",
    title: "Advanced Comparative Theology",
    difficulty: "Advanced",
    category: "Advanced Dawah Scenarios",
    description: "Maya, a graduate student in comparative literature, raises complex objections about secular morals and scriptural canonization, testing your capacity to frame the Quran as the ultimate criterion (Furqan).",
    characterName: "Maya",
    characterRole: "MA Comparative Literature Candidate",
    avatarSeed: "maya",
    initialNodeId: "comp_intro",
    nodes: {
      comp_intro: {
        id: "comp_intro",
        characterText: "Hi. I study comparative texts and post-secular ethics. My question is regarding moral authority. Secular societies have successfully evolved moral concepts like human rights, equality, and state neutralism without needing divine threat. Isn't a secular, fluid ethical framework vastly superior to an ancient, rigid canon that cannot adapt to modern progress?",
        characterTone: "skeptical",
        assistantInstruction: "Engage her on the objective foundation of ethics. Ask her how a secular framework anchors objective values without dissolving into subjective taste or cultural relativism.",
        options: [
          {
            id: "comp_opt_ethics",
            text: "Challenge respectfully: 'That is the central puzzle of secular ethics! From an evolutionary or social standpoint, morals are fluid conventions that change. But if morals are fluid, then things like human rights are just temporary cultural fashions, not objective facts. How do we anchor absolute human rights without a transcendent moral Giver?'",
            score: 100,
            nextNodeId: "comp_moral_foundation",
            outcomeSummary: "Maya appreciates the rigorous philosophical approach and admits that the grounding problem of secular ethics is a massive contemporary debate.",
            emotionalFeedback: {
              characterToneImpact: "thoughtful",
              atmosphereChange: "Maya shifts to a highly focused, intellectual state."
            },
            mentorFeedback: {
              mentorName: "Dr. Tariq",
              scoreExplanation: "Fascinating work. Placing the burden of anchoring objective truth on secularism is the correct philosophical approach when discussing comparative ethics with graduate students.",
              encouragement: "Excellent intellectual poise!",
              improvementSuggestions: [
                "Excellent grounding! Ask her about the foundational standard used to resolve cross-cultural conflicts if all secular values are subjective expressions."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Recognized her background and addressed her on her level." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Solid deconstruction of secular relativism." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Masterful academic poise." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "Welcome Maya! You raise a critical question in post-secular ethics. If we say human rights are fluid, then we admit they have no stable foundation. If society changes its mind tomorrow, human rights could dissolve. Islam anchors these rights in the Divine, protecting them from mortal politics.",
              whyItWorks: "Anchors the divine code as a protection for human rights rather than a restriction.",
              improvementTips: [],
              recommendedLessons: [
                { title: "Secular Grounding & Islamic Ethics", moduleUrl: "/courses/secular-critique-advanced", difficulty: "Advanced" }
              ],
              relatedResources: []
            }
          }
        ]
      },
      comp_moral_foundation: {
        id: "comp_moral_foundation",
        characterText: "Good point. Grounding objective value is indeed the Achilles' heel of secularism. But doesn't declaring a scripture as an absolute authority lead to an authoritarian freeze, where humans surrender their critical agency to follow texts blindly?",
        characterTone: "thoughtful",
        assistantInstruction: "Show how Quranic authority actually stimulates and guides critical agency, asking humans to reason, contemplate, and deduce wisdom rather than freeze it.",
        options: [
          {
            id: "comp_opt_agency",
            text: "Point out: 'Actually, the Quran constantly asks us to reason (Afala Ta'qilun: will you not reason?). Divine law (Shariah) acts as a structural border, but within those borders, human intellect is fully unleashed to innovate, study, and benefit humanity.'",
            score: 100,
            nextNodeId: null,
            outcomeSummary: "Maya is impressed of the distinction between blind dogmatism and guided intellectual inquiry.",
            emotionalFeedback: {
              characterToneImpact: "receptive",
              atmosphereChange: "The scholarly conversation resolves with profound mutual respect."
            },
            mentorFeedback: {
              mentorName: "Dr. Tariq",
              scoreExplanation: "Pristine intellectual dialogue. You demonstrated that revelation does not lock the mind, but rather forms the compass that allows the mind to navigate life safely.",
              encouragement: "Glaser index level work! May Allah continue to guide your mind.",
              improvementSuggestions: [
                "Excellent! Tie this to early Islamic scientific flourishing which occurred exactly because of this philosophical worldview."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Superb matching of academic language registers." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 15, feedback: "Excellent synthesis of reason (Aql) and revelation (Naql)." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Ultimate high standard of scholarly dialogue." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "In Islam, revelation is the lens that sharpens reason. It is not an authoritarian shutoff; it is a GPS that lets the driver travel securely with absolute confidence.",
              whyItWorks: "A highly clear GPS analogy.",
              improvementTips: [],
              recommendedLessons: [],
              relatedResources: []
            }
          }
        ],
        reflectionPrompt: {
          question: "How can we present Divine rules as a shield for human rights rather than a rigid restriction?",
          placeholder: "Reflect on divine borders versus fluid social models.",
          mentorSampleAnswer: "When rules are anchored in the divine, they are completely safe from political tides. This turns divine laws into an absolute shield for human dignity, rather than arbitrary restrictions."
        }
      }
    }
  },
  {
    id: "women-rights-islam",
    title: "Gender, Justice & Prophetic Ethics",
    difficulty: "Intermediate",
    category: "Misconceptions",
    description: "Chloe, an SFU Gender Studies major, questions the status and rights of women in Islamic jurisprudence, pointing out standard misconceptions regarding inheritance, leadership, and modesty. Practice addressing her concerns using historic context, classical legal maxims, and profound prophetic adab.",
    characterName: "Chloe",
    characterRole: "SFU Gender Studies Major",
    avatarSeed: "chloe",
    initialNodeId: "women_intro",
    nodes: {
      women_intro: {
        id: "women_intro",
        characterText: "Hello there. I see your pamphlets on moral family structures, but looking from a modern human rights standpoint, traditional religions like Islam seem structurally patriarchal. Critics point to different inheritance shares for men and women, modesty rules that seem aimed solely at women, and limited leadership roles. How does a progressive mind square absolute gender justice with these medieval texts?",
        characterTone: "skeptical",
        assistantInstruction: "Validate her concern for gender justice. Distinguish between 'sameness' and 'functional equity' under divine wisdom, pointing out how classical Islamic law guaranteed women property rights and bodily autonomy over 1400 years before Western legal codes did.",
        options: [
          {
            id: "women_opt_empathy",
            text: "Validate her ethical intent. Agree that protecting women's safety, dignity, and rights is a non-negotiable Islamic duty. Clarify that Islam views spiritual worth as identical while distributing financial and social responsibilities equitably.",
            score: 100,
            nextNodeId: "women_equity_logic",
            outcomeSummary: "Chloe respects your historical framework and is open to examining the traditional division of duties.",
            emotionalFeedback: {
              characterToneImpact: "thoughtful",
              atmosphereChange: "Chloe nods, crossing her arms defensively but showing academic curiosity as you avoid a defensive reaction."
            },
            mentorFeedback: {
              mentorName: "Sister Yasmin",
              scoreExplanation: "Superb. Starting with shared values—the absolute dignity and protection of women—instantly dismantles the myth that Islam degrades women. This builds immediate trust.",
              encouragement: "Excellent adab, sister. Your validation of her moral desire for justice is pure prophetic wisdom.",
              improvementSuggestions: [
                "Mention how pre-Islamic societies treated women as property, to establish historical contrast."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Superb active listening and validation." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Correctly framed identical spiritual values under Allah." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Very polished, collaborative dialogue posture." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "Chloe, I really respect your focus on gender justice. Frankly, if a religious system truly degraded half of humanity, I would reject it too. In Islam, God states that men and women are spiritual twins. But we distinguish between 'equality in value' and 'sameness in function' to ensure women are financially protected, not exploited.",
              whyItWorks: "It establishes that you share her desire for justice and introduces the key distinction between value and function.",
              improvementTips: [
                "Avoid starting with defensive disclaimers. Use terms like 'spiritual twins' (based on the Hadith: 'Verily, women are the twins of men')."
              ],
              recommendedLessons: [
                { title: "Women in Islamic Law and History", moduleUrl: "/courses/women-islam", difficulty: "Intermediate" }
              ],
              relatedResources: [
                { name: "The Myth of Islamic Patriarchy - Article", type: "article", url: "/resources/myth-patriarchy" }
              ]
            }
          },
          {
            id: "women_opt_defensive",
            text: "Adopt a defensive tone. Tell her she is judging Islam through the subjective lens of Western modernism, which is a temporary and unstable ideology, and that she has no right to cross-examine God's law.",
            score: 55,
            nextNodeId: "women_defensive_trap",
            outcomeSummary: "Chloe becomes highly agitated, feeling intellectually locked out by dogmatic barriers.",
            emotionalFeedback: {
              characterToneImpact: "agitated",
              atmosphereChange: "Chloe wrinkles her forehead and steps away slightly, preparing to dismiss the dialogue."
            },
            mentorFeedback: {
              mentorName: "Dr. Tariq",
              scoreExplanation: "Your philosophical point that Western values are fluid is correct, but your posture was hostile. We are here to disarm skeptics, not to scold them.",
              encouragement: "You have strong deconstruction tools, let's use them with softer, more inviting adab.",
              improvementSuggestions: [
                "De-escalate. Apologize for sounding combative and address her original points on equity and inheritance."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 3, maxScore: 10, feedback: "Highly aggressive and missed her moral intent." },
                { rubric: "Theological Accuracy", score: 8, maxScore: 10, feedback: "Point about divine sovereignty is solid but poorly positioned." },
                { rubric: "Dialogue Posture", score: 4, maxScore: 10, feedback: "Dismissive and intellectually combative." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "I appreciate that you want to hold systems accountable. While we can unpack the philosophical challenges of evaluating ancient divine principles through fluid modern lenses, let's look at what the texts actually command regarding women's rights.",
              whyItWorks: "Bridges her critique with rational historical discussion, cooling down the defensive atmosphere.",
              improvementTips: [
                "Never accuse the speaker of being biased initially; instead, invite them to explore together."
              ],
              recommendedLessons: [
                { title: "Prophetic Etiquette in Public Debates", moduleUrl: "/courses/etiquette", difficulty: "Intermediate" }
              ],
              relatedResources: []
            }
          }
        ]
      },
      women_equity_logic: {
        id: "women_equity_logic",
        characterText: "Okay, that's a refreshing distinction. But how does that work in practice? If a daughter gets half the inheritance of a son, or if modest dress codes are so heavily enforced on women, how is that not structural inequality in daily life?",
        characterTone: "thoughtful",
        assistantInstruction: "Deconstruct the inheritance and financial structure in Islam. Explain that under Islamic jurisprudence (Fiqh), men are legally mandated to pay all family expenses (Nafaqah), while women's income and inheritance is 100% their private, untaxed property.",
        options: [
          {
            id: "women_opt_finance",
            text: "Explain the legal distribution of financial liabilities: under Islamic law, men must completely cover the housing, food, and medical costs of all female relatives (Nafaqah). A woman's money is entirely hers to invest and keep, making her inheritance net-positive in real-world economics.",
            score: 100,
            nextNodeId: "women_modesty_resolution",
            outcomeSummary: "Chloe is surprised to learn about the legal financial imbalance that favors women and understands the systemic logic of different inheritance shares.",
            emotionalFeedback: {
              characterToneImpact: "receptive",
              atmosphereChange: "Chloe leans on the podium with a curious expression, processing the financial liabilities structure."
            },
            mentorFeedback: {
              mentorName: "Sister Yasmin",
              scoreExplanation: "Mashallah, a pristine conceptual explanation. Showing that legal obligations balance the inheritance division dissolves the accusation of bias.",
              encouragement: "Outstanding explanation. You demonstrated depth and precision in traditional jurisprudence.",
              improvementSuggestions: [
                "You can contrast this with early European common law where a woman's assets merged entirely with her husband's."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Very respectful, welcoming, and responsive." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Perfect representation of Nafaqah law in Shariah." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Maintained clear academic focus." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "In Shariah, Chloe, financial rights are tied to financial duties. A married man is legally required to spend his wealth on his wife and children. A woman's wealth is her own private property—she doesn't have to spend a single penny on household expenses. When you look at the total economic cycle, the daughter's net capital retention is often higher.",
              whyItWorks: "Turns an apparent deficit into a systemic financial privilege, satisfying systemic-minded thinkers.",
              improvementTips: [
                "Explain Nafaqah simply without overly complex Arabic terms."
              ],
              recommendedLessons: [
                { title: "The Family Financial Structure in Islam", moduleUrl: "/courses/family-systems", difficulty: "Intermediate" }
              ],
              relatedResources: []
            }
          }
        ]
      },
      women_defensive_trap: {
        id: "women_defensive_trap",
        characterText: "That's exactly what concerns me about organized religion. You declare your interpretation to be divine, which shuts down any authentic conversation. If you can't engage with contemporary criticism respectfully, how can you claim to be a valid resource for modern students?",
        characterTone: "hostile",
        assistantInstruction: "Perform an intellectual recovery. Apologize for sounding defensive, validate her frustration with dogmatic stonewalling, and redirect back to the legal details of women's historical status.",
        options: [
          {
            id: "women_opt_recovery",
            text: "Humbly apologize: 'You are completely right, Chloe. I answered defensively and that was wrong of me. I apologize. Sincere inquiry is the lifeblood of classical Islam. Let's backtrack. The Prophet actually taught that seeking knowledge is a duty for every single soul, male or female. Let me answer your questions on inheritance and modesty directly with their legal contexts.'",
            score: 95,
            nextNodeId: "women_equity_logic",
            outcomeSummary: "Chloe respects your intellectual humility and agrees to hear the financial equity details.",
            emotionalFeedback: {
              characterToneImpact: "calm",
              atmosphereChange: "Chloe's defensive expression softens. Your humility disarms her combative momentum completely."
            },
            mentorFeedback: {
              mentorName: "Sister Yasmin",
              scoreExplanation: "Fabulous recovery. Admitting fault is the ultimate proof of sincerity (Ikhlas). You prioritize her heart over your pride.",
              encouragement: "Extremely proud of your humility, student. This is the mark of a true caller to Allah.",
              improvementSuggestions: [
                "Transition cleanly to the Nafaqah explanation."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Exceptional adab and humility." },
                { rubric: "Theological Accuracy", score: 9, maxScore: 10, feedback: "Corrected the path for faithful engagement." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Highest honors in relational character." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "I am really sorry, Chloe. I sounded combative, and that is not the prophetic way of showing respect. Sincere questioning is what helped early scholars compile our entire creed. Let's look at why different rights are distributed the way they are.",
              whyItWorks: "Builds a bridge and re-licenses her critical inquiries as scholastic assets.",
              improvementTips: [],
              recommendedLessons: [],
              relatedResources: []
            }
          }
        ]
      },
      women_modesty_resolution: {
        id: "women_modesty_resolution",
        characterText: "That... makes a lot of sense from a legal perspective. I didn't realize women had absolute financial autonomy in classical Islam! But what about modesty? Why are women expected to cover while men have far simpler requirements?",
        characterTone: "curious",
        assistantInstruction: "Frame Islamic modesty (Haya) as an anti-consumerist, spiritual boundary. Show that the Quran commands men to 'lower their gaze' first before telling women to cover, making modesty a shared ethical eco-system that repels objectification.",
        options: [
          {
            id: "women_opt_modesty",
            text: "Explain the ecosystem of modesty (Haya): it is a shared spiritual shield. Point out that in the Quran (Surah An-Nur), God commands men to 'lower their gaze' first to protect women from predatory visual consumption, and then commands women to guard their beauty to cultivate a society of spiritual substance over cosmetic objectification.",
            score: 100,
            nextNodeId: null,
            outcomeSummary: "Chloe is highly receptive, recognizing that Islamic modesty opposes the hyper-sexualized, commercial exploitation of women's bodies.",
            emotionalFeedback: {
              characterToneImpact: "receptive",
              atmosphereChange: "Chloe smiles softly, taking an outreach flyer and shaking hands warmly with deep respect."
            },
            mentorFeedback: {
              mentorName: "Sister Yasmin",
              scoreExplanation: "Mashallah, a masterpiece of a dialogue! Framing modesty as resistance against capitalist objectification was exceptionally relevant. You helped a modern student view Islam as a liberator of women.",
              encouragement: "Superb wisdom, student! May Allah reward your eloquence and sincerity.",
              improvementSuggestions: [
                "End the dialogue on an open invitation. Offer her a copy of 'The Clear Quran' as a gesture of appreciation."
              ],
              criteriaScores: [
                { rubric: "Prophetic Empathy", score: 10, maxScore: 10, feedback: "Exceptional emotional modeling." },
                { rubric: "Theological Accuracy", score: 10, maxScore: 10, feedback: "Spot-on explanation of Haya and Surah An-Nur." },
                { rubric: "Dialogue Posture", score: 10, maxScore: 10, feedback: "Flawless interfaith guidance posture." }
              ]
            },
            aiSuggestions: {
              suggestedResponse: "In Islam, Chloe, modesty (Haya) is an act of resistance. In a world that pressures women to constantly commodify their bodies for commercial consumption, the hijab states: 'My body is not public property. You must judge me for my mind, my ethics, and my soul.' And the Quran mandates men to master their eyes first, making it a shared responsibility.",
              whyItWorks: "Uses modern sociological phrases that a gender-studies major finds incredibly compelling and validating.",
              improvementTips: [
                "Focus on liberation from consumerism as a powerful bridge."
              ],
              recommendedLessons: [
                { title: "Modesty, Hijab, and Spiritual Substance", moduleUrl: "/courses/modesty", difficulty: "Beginner" }
              ],
              relatedResources: [
                { name: "Hijab as Liberation - Booklet", type: "guide", url: "/resources/hijab-liberation" }
              ]
            }
          }
        ],
        reflectionPrompt: {
          question: "How does framing modesty as an act of resistance against gender commodification build a bridge to secular students, and why are classical sources essential for backing this up?",
          placeholder: "Write your reflection here. Compare spiritual boundaries with modern commercial pressures on women.",
          mentorSampleAnswer: "By explaining Haya as resistance to sexual commodification, we translate a traditional practice into a solution for a modern ethical crisis. Grounding it in classical rulings prevents it from sounding like our private modern opinion, preserving traditional authority."
        }
      }
    }
  }
];

export type { PracticeScenario };
export { BRANDED_SCENARIOS as mockScenarios };
